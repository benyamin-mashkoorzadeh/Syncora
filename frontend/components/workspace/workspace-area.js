"use client";

import { useAuth } from "@/components/auth/auth-provider";
import BrandMark from "@/components/brand-mark";
import NotificationCenter from "@/components/notification/notification-center";
import ThemeToggle from "@/components/theme-toggle";
import UserAvatar from "@/components/user-avatar";
import ProjectSurface from "@/components/project/project-surface";
import TaskBoard from "@/components/task/task-board";
import TaskDetail from "@/components/task/task-detail";
import TeamChat from "@/components/chat/team-chat";
import { authApi } from "@/lib/auth-api";
import { ApiError } from "@/lib/api-client";
import { workspaceApi } from "@/lib/workspace-api";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import styles from "./workspace.module.css";

export default function WorkspaceArea({ slug, view = "overview", projectSlug = null, taskId = null }) {
  const router = useRouter();
  const { status, user, clearUser } = useAuth();
  const [state, setState] = useState({ loading: true, error: "", code: 0, workspaces: [], workspace: null });
  const [members, setMembers] = useState([]);
  const [membersState, setMembersState] = useState({ loading: view === "members", error: "" });

  const load = useCallback(async (signal) => {
    try {
      const [listPayload, workspacePayload] = await Promise.all([
        workspaceApi.list({ signal }),
        workspaceApi.show(slug, { signal }),
      ]);
      setState({ loading: false, error: "", code: 0, workspaces: listPayload.data, workspace: workspacePayload.data });
      if (view === "members") {
        try {
          const payload = await workspaceApi.members(slug, { signal });
          setMembers(payload.data);
          setMembersState({ loading: false, error: "" });
        } catch (error) {
          if (error.name !== "AbortError") setMembersState({ loading: false, error: error.message });
        }
      }
    } catch (error) {
      if (error.name === "AbortError") return;
      if (error instanceof ApiError && error.status === 401) {
        clearUser();
        router.replace("/login");
        return;
      }
      if (error instanceof ApiError && error.status === 403) {
        router.replace("/verify-email");
        return;
      }
      setState({ loading: false, error: error.message, code: error.status ?? 0, workspaces: [], workspace: null });
    }
  }, [clearUser, router, slug, view]);

  useEffect(() => {
    if (status === "unauthenticated") router.replace("/login");
    if (status === "authenticated" && !user.is_verified) router.replace("/verify-email");
    if (status !== "authenticated" || !user.is_verified) return;
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load(controller.signal), 0);
    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [load, router, status, user]);

  async function logout() {
    try { await authApi.logout(); } finally { clearUser(); router.replace("/login"); }
  }

  if (status === "loading" || state.loading || !user) return <Status message="Loading your workspace…" />;
  if (state.error) return <Status title={state.code === 404 ? "Workspace not found" : "Workspace unavailable"} message={state.code === 404 ? "You don’t have access to this workspace, or it no longer exists." : state.error} action={() => router.push("/workspaces")} />;

  const workspace = state.workspace;
  return (
    <div className={styles.app}>
      <div className={styles.shell}>
        <aside className={styles.sidebar}>
          <Link className={styles.brandLink} href={`/${workspace.slug}`}><BrandMark className={styles.brandMark} />Syncora</Link>
          <select className={styles.workspaceSelect} value={workspace.slug} onChange={(event) => router.push(`/${event.target.value}`)} aria-label="Current workspace">
            {state.workspaces.map((item) => <option value={item.slug} key={item.id}>{item.name}</option>)}
          </select>
          <Link className={styles.createLink} href="/workspaces/new">+ Create workspace</Link>
          <nav className={styles.nav} aria-label="Workspace navigation">
            <Link className={view === "overview" ? styles.active : ""} href={`/${workspace.slug}`}>Overview</Link>
            <Link className={["projects", "project", "board", "task", "chat"].includes(view) ? styles.active : ""} href={`/${workspace.slug}/projects`}>Projects</Link>
            <Link className={view === "members" ? styles.active : ""} href={`/${workspace.slug}/members`}>Members</Link>
            <Link className={view === "settings" ? styles.active : ""} href={`/${workspace.slug}/settings`}>Settings</Link>
          </nav>
          <div className={styles.account}>
            <UserAvatar name={user.name} src={user.avatar_url} />
            <div className={styles.accountDetails}><strong>{user.name}</strong><span>{user.email}</span></div>
            <div className={styles.sidebarActions}><Link href="/account">Account</Link><button type="button" onClick={logout}>Sign out</button></div>
          </div>
        </aside>
        <main className={styles.main}>
          <header className={styles.topbar}>
            <span className={styles.mobileBrand}><BrandMark className={styles.brandMark} />Syncora</span>
            <strong>{workspace.name}</strong>
            <div className={styles.topbarActions}><NotificationCenter user={user} /><ThemeToggle /></div>
          </header>
          <div className={styles.content}>
            {view === "overview" ? <Overview workspace={workspace} /> : null}
            {view === "projects" ? <ProjectSurface workspace={workspace} /> : null}
            {view === "project" ? <ProjectSurface workspace={workspace} projectSlug={projectSlug} /> : null}
            {view === "board" ? <TaskBoard workspace={workspace} projectSlug={projectSlug} /> : null}
            {view === "task" ? <TaskDetail workspace={workspace} projectSlug={projectSlug} taskId={taskId} currentUser={user} /> : null}
            {view === "chat" ? <TeamChat workspace={workspace} projectSlug={projectSlug} currentUser={user} /> : null}
            {view === "members" ? <Members workspace={workspace} members={members} setMembers={setMembers} state={membersState} /> : null}
            {view === "settings" ? <Settings workspace={workspace} setWorkspace={(next) => setState((current) => ({ ...current, workspace: next, workspaces: current.workspaces.map((item) => item.id === next.id ? next : item) }))} /> : null}
          </div>
        </main>
      </div>
    </div>
  );
}

function Overview({ workspace }) {
  return <>
    <p className={styles.eyebrow}>Workspace overview</p>
    <h1 className={styles.pageTitle}>Welcome to {workspace.name}.</h1>
    <p className={styles.pageDescription}>This is your team’s collaboration boundary in Syncora. Shape focused projects, then plan the sprints that move them forward.</p>
    <div className={styles.overviewGrid}>
      <article className={`${styles.card} ${styles.cardAccent}`}><h2>Plan meaningful work</h2><p>Create a project for each focused initiative, then organize its delivery through clear sprint goals.</p><Link href={`/${workspace.slug}/projects`}>View projects →</Link><div className={styles.meta}><span className={styles.badge}>{workspace.membership.role}</span><span className={styles.badge}>{workspace.member_count} {workspace.member_count === 1 ? "member" : "members"}</span></div></article>
      <article className={styles.card}><h2>Build the team</h2><p>See who has access and, if you’re the owner, add an existing Syncora user.</p><Link href={`/${workspace.slug}/members`}>Manage members →</Link></article>
    </div>
  </>;
}

function Members({ workspace, members, setMembers, state }) {
  const isOwner = workspace.membership.role === "owner";
  const [email, setEmail] = useState("");
  const [pending, setPending] = useState(false);
  const [removing, setRemoving] = useState(null);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  async function add(event) {
    event.preventDefault(); setPending(true); setError(""); setMessage("");
    try { const payload = await workspaceApi.addMember(workspace.slug, email); setMembers((current) => [...current, payload.data]); setEmail(""); setMessage(payload.message); }
    catch (requestError) { setError(requestError.errors?.email?.[0] ?? requestError.message); }
    finally { setPending(false); }
  }

  async function remove(member) {
    setRemoving(member.id); setError(""); setMessage("");
    try { const payload = await workspaceApi.removeMember(workspace.slug, member.id); setMembers((current) => current.filter((item) => item.id !== member.id)); setMessage(payload.message); }
    catch (requestError) { setError(requestError.errors?.member?.[0] ?? requestError.message); }
    finally { setRemoving(null); }
  }

  return <>
    <p className={styles.eyebrow}>People and access</p>
    <div className={styles.sectionHeader}><div><h1 className={styles.pageTitle}>Members</h1><p className={styles.pageDescription}>Everyone currently authorized to collaborate in {workspace.name}.</p></div></div>
    <div className={styles.membersLayout}>
      <section className={styles.memberList} aria-label="Workspace members">
        {state.loading ? <p className={styles.empty} role="status">Loading members…</p> : null}
        {state.error ? <p className={styles.empty} role="alert">{state.error}</p> : null}
        {!state.loading && !state.error && !members.length ? <p className={styles.empty}>No workspace members found.</p> : null}
        {members.map((member) => <div className={styles.memberRow} key={member.id}><UserAvatar name={member.user.name} src={member.user.avatar_url} /><div className={styles.memberIdentity}><strong>{member.user.name}</strong><span>{member.user.email}</span></div><span className={styles.role}>{member.role}</span>{isOwner && member.role !== "owner" ? <button className={styles.dangerButton} type="button" disabled={removing === member.id} onClick={() => remove(member)}>{removing === member.id ? "Removing…" : "Remove"}</button> : null}</div>)}
      </section>
      <aside className={styles.card}>
        <h2>{isOwner ? "Add an existing user" : "Workspace access"}</h2>
        <p>{isOwner ? "Enter the email address of a registered Syncora account." : "Only the workspace owner can add or remove members."}</p>
        {isOwner ? <form className={styles.form} onSubmit={add}><div className={styles.field}><label htmlFor="member-email">Email address</label><input id="member-email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="teammate@company.com" required /></div><button className={styles.primaryButton} type="submit" disabled={pending}>{pending ? "Adding member…" : "Add member"}</button></form> : null}
        {error ? <p className={styles.noticeError} role="alert">{error}</p> : null}{message ? <p className={styles.noticeSuccess} role="status">{message}</p> : null}
      </aside>
    </div>
  </>;
}

function Settings({ workspace, setWorkspace }) {
  const [name, setName] = useState(workspace.name);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const isOwner = workspace.membership.role === "owner";
  async function save(event) { event.preventDefault(); setPending(true); setError(""); setMessage(""); try { const payload = await workspaceApi.update(workspace.slug, { name }); setWorkspace(payload.data); setMessage(payload.message); } catch (requestError) { setError(requestError.errors?.name?.[0] ?? requestError.message); } finally { setPending(false); } }
  return <><p className={styles.eyebrow}>Workspace configuration</p><h1 className={styles.pageTitle}>Settings</h1><p className={styles.pageDescription}>Keep the workspace identity clear. Its URL stays stable when the name changes.</p><section className={`${styles.card} ${styles.settingsCard}`}><h2>Workspace name</h2>{isOwner ? <form className={styles.form} onSubmit={save}><div className={styles.field}><label htmlFor="settings-name">Name</label><input id="settings-name" value={name} onChange={(event) => setName(event.target.value)} required /></div><button className={styles.primaryButton} type="submit" disabled={pending}>{pending ? "Saving…" : "Save changes"}</button></form> : <p>Only the workspace owner can change settings.</p>}{error ? <p className={styles.noticeError} role="alert">{error}</p> : null}{message ? <p className={styles.noticeSuccess} role="status">{message}</p> : null}</section></>;
}

function Status({ title, message, action }) {
  return <main className={styles.status}><div className={styles.statusInner}>{title ? <h1>{title}</h1> : null}<p>{message}</p>{action ? <button className={styles.secondaryButton} type="button" onClick={action}>Back to my workspaces</button> : null}</div></main>;
}
