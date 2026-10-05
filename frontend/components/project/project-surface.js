"use client";

import { ApiError } from "@/lib/api-client";
import { projectApi } from "@/lib/project-api";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import styles from "./project.module.css";

const emptyProject = { name: "", description: "" };
const emptySprint = { name: "", goal: "", start_date: "", end_date: "" };

export default function ProjectSurface({ workspace, projectSlug = null }) {
  return projectSlug
    ? <ProjectDetail workspace={workspace} projectSlug={projectSlug} />
    : <ProjectIndex workspace={workspace} />;
}

function ProjectIndex({ workspace }) {
  const router = useRouter();
  const isOwner = workspace.membership.role === "owner";
  const [state, setState] = useState({ loading: true, error: "", projects: [], meta: null });
  const [form, setForm] = useState(emptyProject);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState("");
  const [pending, setPending] = useState(false);

  const load = useCallback(async (page = 1, signal) => {
    setState((current) => ({ ...current, loading: true, error: "" }));
    try {
      const payload = await projectApi.list(workspace.slug, { page, signal });
      setState({ loading: false, error: "", projects: payload.data, meta: payload.meta });
    } catch (error) {
      if (error.name !== "AbortError") {
        setState({ loading: false, error: error.message, projects: [], meta: null });
      }
    }
  }, [workspace.slug]);

  useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load(1, controller.signal), 0);
    return () => { window.clearTimeout(timeout); controller.abort(); };
  }, [load]);

  async function createProject(event) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setFormError("");
    try {
      const payload = await projectApi.create(workspace.slug, form);
      router.push(`/${workspace.slug}/projects/${payload.data.slug}`);
    } catch (error) {
      setErrors(error.errors ?? {});
      setFormError(Object.keys(error.errors ?? {}).length ? "" : error.message);
    } finally {
      setPending(false);
    }
  }

  return <>
    <div className={styles.headingRow}>
      <div>
        <p className={styles.eyebrow}>Focused initiatives</p>
        <h1 className={styles.pageTitle}>Projects</h1>
        <p className={styles.pageDescription}>Give each initiative a clear home, then shape its delivery through focused sprints.</p>
      </div>
      <span className={styles.accessBadge}>{isOwner ? "Owner access" : "View only"}</span>
    </div>

    <div className={isOwner ? styles.indexLayout : styles.singleColumn}>
      <section aria-labelledby="project-list-title">
        <div className={styles.sectionHeading}>
          <div><p className={styles.kicker}>Workspace projects</p><h2 id="project-list-title">What your team is shaping</h2></div>
          {!state.loading && !state.error ? <span className={styles.count}>{state.meta?.total ?? state.projects.length}</span> : null}
        </div>

        {state.loading ? <LoadingState label="Loading projects…" /> : null}
        {!state.loading && state.error ? <ErrorState message={state.error} retry={() => load()} /> : null}
        {!state.loading && !state.error && !state.projects.length ? <EmptyState title="A clear place to begin" body={isOwner ? "Create the first project for this workspace. You can add its delivery sprints once the project is ready." : "This workspace does not have any projects yet. The workspace owner can create the first one."} /> : null}
        {!state.loading && !state.error && state.projects.length ? <div className={styles.projectGrid}>
          {state.projects.map((project) => <Link className={styles.projectCard} href={`/${workspace.slug}/projects/${project.slug}`} key={project.id}>
            <span className={styles.projectMark} aria-hidden="true">{project.name.trim().charAt(0).toUpperCase() || "P"}</span>
            <div><h3>{project.name}</h3><p>{project.description || "No description has been added yet."}</p></div>
            <span className={styles.cardArrow} aria-hidden="true">↗</span>
          </Link>)}
        </div> : null}
        <Pagination meta={state.meta} onPage={(page) => load(page)} label="Projects" />
      </section>

      {isOwner ? <aside className={styles.formCard}>
        <p className={styles.kicker}>New project</p>
        <h2>Create a focused space</h2>
        <p>Use a concise name and enough context for the team to understand the initiative.</p>
        <form className={styles.form} onSubmit={createProject} noValidate>
          <Field label="Project name" error={errors.name?.[0]}>
            <input id="project-name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} maxLength={100} required autoComplete="off" />
          </Field>
          <Field label="Description" error={errors.description?.[0]} optional>
            <textarea id="project-description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} maxLength={5000} rows={5} />
          </Field>
          {formError ? <p className={styles.formError} role="alert">{formError}</p> : null}
          <button className={styles.primaryButton} type="submit" disabled={pending}>{pending ? "Creating project…" : "Create project"}</button>
        </form>
      </aside> : null}
    </div>
  </>;
}

function ProjectDetail({ workspace, projectSlug }) {
  const router = useRouter();
  const isOwner = workspace.membership.role === "owner";
  const [state, setState] = useState({ loading: true, error: "", code: 0, project: null, sprints: [], meta: null });
  const [editingProject, setEditingProject] = useState(false);
  const [projectForm, setProjectForm] = useState(emptyProject);
  const [projectErrors, setProjectErrors] = useState({});
  const [projectMessage, setProjectMessage] = useState("");
  const [projectPending, setProjectPending] = useState(false);
  const [sprintForm, setSprintForm] = useState(emptySprint);
  const [sprintErrors, setSprintErrors] = useState({});
  const [sprintError, setSprintError] = useState("");
  const [sprintPending, setSprintPending] = useState(false);

  const load = useCallback(async (page = 1, signal) => {
    setState((current) => ({ ...current, loading: true, error: "" }));
    try {
      const [projectPayload, sprintPayload] = await Promise.all([
        projectApi.show(workspace.slug, projectSlug, { signal }),
        projectApi.sprints(workspace.slug, projectSlug, { page, signal }),
      ]);
      setState({ loading: false, error: "", code: 0, project: projectPayload.data, sprints: sprintPayload.data, meta: sprintPayload.meta });
      setProjectForm({ name: projectPayload.data.name, description: projectPayload.data.description ?? "" });
    } catch (error) {
      if (error.name !== "AbortError") {
        setState({ loading: false, error: error.message, code: error.status ?? 0, project: null, sprints: [], meta: null });
      }
    }
  }, [projectSlug, workspace.slug]);

  useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load(1, controller.signal), 0);
    return () => { window.clearTimeout(timeout); controller.abort(); };
  }, [load]);

  async function saveProject(event) {
    event.preventDefault();
    setProjectPending(true);
    setProjectErrors({});
    setProjectMessage("");
    try {
      const payload = await projectApi.update(workspace.slug, projectSlug, projectForm);
      setState((current) => ({ ...current, project: payload.data }));
      setEditingProject(false);
      setProjectMessage("Project details saved. Its URL remains unchanged.");
    } catch (error) {
      setProjectErrors(error.errors ?? { request: [error.message] });
    } finally {
      setProjectPending(false);
    }
  }

  async function createSprint(event) {
    event.preventDefault();
    setSprintPending(true);
    setSprintErrors({});
    setSprintError("");
    try {
      await projectApi.createSprint(workspace.slug, projectSlug, sprintForm);
      setSprintForm(emptySprint);
      await load(1);
    } catch (error) {
      setSprintErrors(error.errors ?? {});
      setSprintError(Object.keys(error.errors ?? {}).length ? "" : error.message);
    } finally {
      setSprintPending(false);
    }
  }

  if (state.loading && !state.project) return <LoadingState label="Loading project…" />;
  if (state.error) return <ErrorState title={state.code === 404 ? "Project not found" : "Project unavailable"} message={state.code === 404 ? "This project does not belong to the current workspace, or you no longer have access." : state.error} retry={state.code === 404 ? null : () => load()} backHref={`/${workspace.slug}/projects`} />;

  const project = state.project;
  return <>
    <Link className={styles.backLink} href={`/${workspace.slug}/projects`}>← All projects</Link>
    <section className={styles.projectHero}>
      <div className={styles.projectIdentity}>
        <span className={styles.largeProjectMark} aria-hidden="true">{project.name.trim().charAt(0).toUpperCase() || "P"}</span>
        <div><p className={styles.eyebrow}>Project</p><h1 className={styles.pageTitle}>{project.name}</h1></div>
      </div>
      <div className={styles.heroActions}>
        <Link className={styles.primaryButton} href={`/${workspace.slug}/projects/${projectSlug}/board`}>Open board</Link>
        <Link className={styles.secondaryButton} href={`/${workspace.slug}/projects/${projectSlug}/chat`}>Team chat</Link>
        {isOwner ? <button className={styles.secondaryButton} type="button" onClick={() => { setEditingProject((value) => !value); setProjectMessage(""); }}>{editingProject ? "Cancel editing" : "Edit project"}</button> : <span className={styles.accessBadge}>View only</span>}
      </div>
      <p className={styles.heroDescription}>{project.description || "No project description has been added yet."}</p>
      {projectMessage ? <p className={styles.successMessage} role="status">{projectMessage}</p> : null}
    </section>

    {editingProject ? <section className={styles.editCard} aria-labelledby="edit-project-title">
      <div><p className={styles.kicker}>Project settings</p><h2 id="edit-project-title">Refine the project details</h2><p>Renaming the project will not change its stable URL.</p></div>
      <form className={styles.form} onSubmit={saveProject} noValidate>
        <Field label="Project name" error={projectErrors.name?.[0]}><input id="edit-project-name" value={projectForm.name} onChange={(event) => setProjectForm({ ...projectForm, name: event.target.value })} maxLength={100} required /></Field>
        <Field label="Description" error={projectErrors.description?.[0]} optional><textarea id="edit-project-description" value={projectForm.description} onChange={(event) => setProjectForm({ ...projectForm, description: event.target.value })} maxLength={5000} rows={4} /></Field>
        {projectErrors.request?.[0] ? <p className={styles.formError} role="alert">{projectErrors.request[0]}</p> : null}
        <button className={styles.primaryButton} type="submit" disabled={projectPending}>{projectPending ? "Saving…" : "Save project"}</button>
      </form>
    </section> : null}

    <div className={isOwner ? styles.sprintLayout : styles.singleColumn}>
      <section aria-labelledby="sprints-title">
        <div className={styles.sectionHeading}>
          <div><p className={styles.kicker}>Delivery rhythm</p><h2 id="sprints-title">Sprints</h2></div>
          {!state.loading ? <span className={styles.count}>{state.meta?.total ?? state.sprints.length}</span> : null}
        </div>
        {state.loading ? <LoadingState label="Refreshing sprints…" compact /> : null}
        {!state.loading && !state.sprints.length ? <EmptyState title="No sprints planned yet" body={isOwner ? "Create the first time-boxed goal for this project. Every new sprint begins in the planned state." : "The project owner has not planned a sprint yet."} compact /> : null}
        {!state.loading && state.sprints.length ? <div className={styles.sprintList}>{state.sprints.map((sprint) => <SprintCard key={sprint.id} sprint={sprint} isOwner={isOwner} workspaceSlug={workspace.slug} projectSlug={projectSlug} onUpdated={(updated) => setState((current) => ({ ...current, sprints: current.sprints.map((item) => item.id === updated.id ? updated : item) }))} />)}</div> : null}
        <Pagination meta={state.meta} onPage={(page) => load(page)} label="Sprints" />
      </section>

      {isOwner ? <aside className={styles.formCard}>
        <p className={styles.kicker}>Plan a sprint</p>
        <h2>Create the next focus</h2>
        <p>Set a clear time window and an optional outcome for the team.</p>
        <form className={styles.form} onSubmit={createSprint} noValidate>
          <Field label="Sprint name" error={sprintErrors.name?.[0]}><input id="sprint-name" value={sprintForm.name} onChange={(event) => setSprintForm({ ...sprintForm, name: event.target.value })} maxLength={100} required /></Field>
          <Field label="Goal" error={sprintErrors.goal?.[0]} optional><textarea id="sprint-goal" value={sprintForm.goal} onChange={(event) => setSprintForm({ ...sprintForm, goal: event.target.value })} maxLength={2000} rows={4} /></Field>
          <div className={styles.dateGrid}>
            <Field label="Start date" error={sprintErrors.start_date?.[0]}><input id="sprint-start" type="date" value={sprintForm.start_date} onChange={(event) => setSprintForm({ ...sprintForm, start_date: event.target.value })} required /></Field>
            <Field label="End date" error={sprintErrors.end_date?.[0]}><input id="sprint-end" type="date" value={sprintForm.end_date} onChange={(event) => setSprintForm({ ...sprintForm, end_date: event.target.value })} required /></Field>
          </div>
          {sprintError ? <p className={styles.formError} role="alert">{sprintError}</p> : null}
          <button className={styles.primaryButton} type="submit" disabled={sprintPending}>{sprintPending ? "Creating sprint…" : "Create planned sprint"}</button>
        </form>
      </aside> : null}
    </div>
  </>;
}

function SprintCard({ sprint, isOwner, workspaceSlug, projectSlug, onUpdated }) {
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({ name: sprint.name, goal: sprint.goal ?? "", start_date: sprint.start_date, end_date: sprint.end_date, status: sprint.status });
  const [errors, setErrors] = useState({});
  const [pending, setPending] = useState(false);
  const statusLabel = sprint.status.charAt(0).toUpperCase() + sprint.status.slice(1);
  const availableStatuses = sprint.status === "planned" ? ["planned", "active"] : ["planned", "active", "completed"];

  async function save(event) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    try {
      const payload = await projectApi.updateSprint(workspaceSlug, projectSlug, sprint.id, form);
      onUpdated(payload.data);
      setForm({ name: payload.data.name, goal: payload.data.goal ?? "", start_date: payload.data.start_date, end_date: payload.data.end_date, status: payload.data.status });
      setEditing(false);
    } catch (error) {
      setErrors(Object.keys(error.errors ?? {}).length ? error.errors : { request: [error.message] });
    } finally {
      setPending(false);
    }
  }

  return <article className={styles.sprintCard}>
    <div className={styles.sprintTopline}>
      <span className={`${styles.statusBadge} ${styles[`status${statusLabel}`]}`}>{statusLabel}</span>
      {isOwner && sprint.status !== "completed" ? <button className={styles.textButton} type="button" onClick={() => setEditing((value) => !value)}>{editing ? "Cancel" : sprint.status === "planned" ? "Edit or start" : "Edit or complete"}</button> : null}
    </div>
    <h3>{sprint.name}</h3>
    <p className={styles.sprintDates}>{formatDate(sprint.start_date)} <span aria-hidden="true">→</span> {formatDate(sprint.end_date)}</p>
    <p className={styles.sprintGoal}>{sprint.goal || "No sprint goal has been added."}</p>
    {sprint.status === "completed" ? <p className={styles.lockedNote}>Completed sprints are preserved as a read-only record.</p> : null}
    {editing ? <form className={`${styles.form} ${styles.inlineForm}`} onSubmit={save} noValidate>
      <Field label="Sprint name" error={errors.name?.[0]}><input id={`sprint-${sprint.id}-name`} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required /></Field>
      <Field label="Goal" error={errors.goal?.[0]} optional><textarea id={`sprint-${sprint.id}-goal`} value={form.goal} onChange={(event) => setForm({ ...form, goal: event.target.value })} rows={3} /></Field>
      <div className={styles.dateGrid}>
        <Field label="Start date" error={errors.start_date?.[0]}><input id={`sprint-${sprint.id}-start`} type="date" value={form.start_date} onChange={(event) => setForm({ ...form, start_date: event.target.value })} required /></Field>
        <Field label="End date" error={errors.end_date?.[0]}><input id={`sprint-${sprint.id}-end`} type="date" value={form.end_date} onChange={(event) => setForm({ ...form, end_date: event.target.value })} required /></Field>
      </div>
      <Field label="Lifecycle" error={errors.status?.[0]}>
        <select id={`sprint-${sprint.id}-status`} value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>{availableStatuses.map((status) => <option value={status} key={status}>{status === "planned" ? "Planned" : status === "active" ? "Active" : "Completed"}</option>)}</select>
      </Field>
      {errors.request?.[0] ? <p className={styles.formError} role="alert">{errors.request[0]}</p> : null}
      <button className={styles.primaryButton} type="submit" disabled={pending}>{pending ? "Saving sprint…" : "Save sprint"}</button>
    </form> : null}
  </article>;
}

function Field({ label, error, optional = false, children }) {
  return <div className={styles.field}>
    <div className={styles.labelRow}><label htmlFor={children.props.id}>{label}</label>{optional ? <span>Optional</span> : null}</div>
    {children}
    {error ? <p className={styles.fieldError} role="alert">{error}</p> : null}
  </div>;
}

function EmptyState({ title, body, compact = false }) {
  return <div className={`${styles.emptyState} ${compact ? styles.compactState : ""}`}><span className={styles.emptyMark} aria-hidden="true">◇</span><h3>{title}</h3><p>{body}</p></div>;
}

function LoadingState({ label, compact = false }) {
  return <div className={`${styles.loadingState} ${compact ? styles.compactState : ""}`} role="status"><span className={styles.spinner} aria-hidden="true" />{label}</div>;
}

function ErrorState({ title = "Something went wrong", message, retry, backHref }) {
  return <div className={styles.errorState} role="alert"><span aria-hidden="true">!</span><div><h2>{title}</h2><p>{message}</p><div className={styles.errorActions}>{retry ? <button className={styles.secondaryButton} type="button" onClick={retry}>Try again</button> : null}{backHref ? <Link className={styles.secondaryButton} href={backHref}>Back to projects</Link> : null}</div></div></div>;
}

function Pagination({ meta, onPage, label }) {
  if (!meta || meta.last_page <= 1) return null;
  return <nav className={styles.pagination} aria-label={`${label} pagination`}>
    <button type="button" disabled={meta.current_page === 1} onClick={() => onPage(meta.current_page - 1)}>← Previous</button>
    <span>Page {meta.current_page} of {meta.last_page}</span>
    <button type="button" disabled={meta.current_page === meta.last_page} onClick={() => onPage(meta.current_page + 1)}>Next →</button>
  </nav>;
}

function formatDate(value) {
  return new Intl.DateTimeFormat("en", { month: "short", day: "numeric", year: "numeric" }).format(new Date(`${value}T00:00:00`));
}
