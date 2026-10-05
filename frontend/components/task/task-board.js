"use client";

import UserAvatar from "@/components/user-avatar";
import { projectApi } from "@/lib/project-api";
import { taskApi } from "@/lib/task-api";
import { useProjectCollaboration } from "@/lib/use-project-collaboration";
import { workspaceApi } from "@/lib/workspace-api";
import {
  closestCorners,
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useDroppable,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import styles from "./task-board.module.css";

const columns = [
  { status: "todo", label: "To Do", note: "Ready to begin" },
  { status: "in_progress", label: "In Progress", note: "Work in motion" },
  { status: "done", label: "Done", note: "Completed work" },
];

const emptyTask = {
  title: "",
  description: "",
  status: "todo",
  priority: "medium",
  sprint_id: "",
  assignee_id: "",
};

export default function TaskBoard({ workspace, projectSlug }) {
  const isOwner = workspace.membership.role === "owner";
  const moveInFlight = useRef(false);
  const formInFlight = useRef(false);
  const realtimeRefreshPending = useRef(false);
  const realtimeRefreshTimer = useRef(null);
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 7 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 220, tolerance: 7 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );
  const [state, setState] = useState({ loading: true, error: "", code: 0, project: null, tasks: [], sprints: [], members: [] });
  const [refreshing, setRefreshing] = useState(false);
  const [mutationError, setMutationError] = useState("");
  const [movingId, setMovingId] = useState(null);
  const [formMode, setFormMode] = useState(null);
  const [form, setForm] = useState(emptyTask);
  const [formErrors, setFormErrors] = useState({});
  const [formError, setFormError] = useState("");
  const [formPending, setFormPending] = useState(false);
  const [activeTask, setActiveTask] = useState(null);
  const [dragTarget, setDragTarget] = useState(null);

  const load = useCallback(async (signal) => {
    try {
      const [projectPayload, taskPayload, sprintPayload, memberPayload] = await Promise.all([
        projectApi.show(workspace.slug, projectSlug, { signal }),
        taskApi.list(workspace.slug, projectSlug, { signal }),
        projectApi.sprints(workspace.slug, projectSlug, { signal }),
        workspaceApi.members(workspace.slug, { signal }),
      ]);
      setState({
        loading: false,
        error: "",
        code: 0,
        project: projectPayload.data,
        tasks: taskPayload.data,
        sprints: sprintPayload.data,
        members: memberPayload.data.map((membership) => membership.user),
      });
    } catch (error) {
      if (error.name !== "AbortError") {
        setState((current) => ({ ...current, loading: false, error: error.message, code: error.status ?? 0 }));
      }
    }
  }, [projectSlug, workspace.slug]);

  const refreshTasks = useCallback(async ({ quiet = false } = {}) => {
    if (!quiet) setRefreshing(true);
    try {
      const payload = await taskApi.list(workspace.slug, projectSlug);
      setState((current) => ({ ...current, tasks: payload.data }));
    } finally {
      if (!quiet) setRefreshing(false);
    }
  }, [projectSlug, workspace.slug]);

  const reconcileRealtime = useCallback(() => {
    realtimeRefreshPending.current = true;
    if (moveInFlight.current || formInFlight.current) return;

    window.clearTimeout(realtimeRefreshTimer.current);
    realtimeRefreshTimer.current = window.setTimeout(async () => {
      realtimeRefreshPending.current = false;
      try {
        await refreshTasks({ quiet: true });
      } catch {
        setMutationError("A live board update could not be reconciled. Your REST actions still work; retry or reload when the connection is stable.");
      }
    }, 120);
  }, [refreshTasks]);

  const collaboration = useProjectCollaboration({
    projectId: state.project?.id,
    onBoardChanged: reconcileRealtime,
    onReconnect: reconcileRealtime,
  });

  useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load(controller.signal), 0);
    return () => { window.clearTimeout(timeout); controller.abort(); };
  }, [load]);

  useEffect(() => () => window.clearTimeout(realtimeRefreshTimer.current), []);

  function startCreate(status = "todo") {
    setForm({ ...emptyTask, status });
    setFormMode({ type: "create" });
    setFormErrors({});
    setFormError("");
  }

  function startEdit(task) {
    setForm({
      title: task.title,
      description: task.description ?? "",
      status: task.status,
      priority: task.priority,
      sprint_id: task.sprint?.id?.toString() ?? "",
      assignee_id: task.assignee?.id?.toString() ?? "",
    });
    setFormMode({ type: "edit", task });
    setFormErrors({});
    setFormError("");
  }

  async function saveTask(event) {
    event.preventDefault();
    formInFlight.current = true;
    setFormPending(true);
    setFormErrors({});
    setFormError("");
    const payload = {
      ...form,
      sprint_id: form.sprint_id ? Number(form.sprint_id) : null,
      assignee_id: form.assignee_id ? Number(form.assignee_id) : null,
    };
    try {
      if (formMode.type === "create") {
        await taskApi.create(workspace.slug, projectSlug, payload);
      } else {
        await taskApi.update(workspace.slug, projectSlug, formMode.task.id, payload);
      }
      await refreshTasks();
      setFormMode(null);
      setForm(emptyTask);
    } catch (error) {
      setFormErrors(error.errors ?? {});
      setFormError(Object.keys(error.errors ?? {}).length ? "" : error.message);
    } finally {
      formInFlight.current = false;
      setFormPending(false);
      if (realtimeRefreshPending.current) reconcileRealtime();
    }
  }

  async function moveTask(task, status, position) {
    if (moveInFlight.current) return;
    moveInFlight.current = true;
    setMovingId(task.id);
    setMutationError("");
    let persisted = false;
    try {
      await taskApi.update(workspace.slug, projectSlug, task.id, { status, position });
      persisted = true;
      await refreshTasks();
    } catch (error) {
      try {
        await refreshTasks();
      } catch {
        // The message below keeps the outcome honest when reconciliation is unavailable.
      }
      setMutationError(persisted
        ? `${task.title} was saved, but the board could not refresh. Reload to reconcile with Laravel.`
        : `${task.title} was not moved. The authoritative board order has been restored. ${error.message}`);
    } finally {
      moveInFlight.current = false;
      setMovingId(null);
      if (realtimeRefreshPending.current) reconcileRealtime();
    }
  }

  function resetDrag() {
    setActiveTask(null);
    setDragTarget(null);
  }

  function handleDragStart({ active }) {
    if (moveInFlight.current) return;
    const task = state.tasks.find((item) => `task:${item.id}` === active.id);
    setActiveTask(task ?? null);
  }

  function handleDragOver({ over }) {
    const data = over?.data.current;
    if (!data) {
      setDragTarget(null);
      return;
    }
    setDragTarget({
      status: data.type === "task" ? data.task.status : data.status,
      taskId: data.type === "task" ? data.task.id : null,
      atEnd: data.type === "column-end",
    });
  }

  async function handleDragEnd({ active, over }) {
    const task = state.tasks.find((item) => `task:${item.id}` === active.id);
    const data = over?.data.current;
    resetDrag();
    if (!task || !data || moveInFlight.current) return;

    const status = data.type === "task" ? data.task.status : data.status;
    const destination = state.tasks.filter((item) => item.status === status);
    const position = data.type === "task"
      ? destination.findIndex((item) => item.id === data.task.id)
      : destination.length;
    const current = state.tasks.filter((item) => item.status === task.status).findIndex((item) => item.id === task.id);

    if (status === task.status && position === current) return;
    await moveTask(task, status, Math.max(position, 0));
  }

  if (state.loading) return <BoardState label="Loading the project board…" />;
  if (state.error) return <BoardError code={state.code} message={state.error} retry={() => load()} workspaceSlug={workspace.slug} />;

  const grouped = Object.fromEntries(columns.map((column) => [column.status, state.tasks.filter((task) => task.status === column.status)]));
  const activeSprint = state.sprints.find((sprint) => sprint.status === "active");

  return <>
    <Link className={styles.backLink} href={`/${workspace.slug}/projects/${projectSlug}`}>← Project overview</Link>
    <header className={styles.boardHeader}>
      <div className={styles.boardIdentity}>
        <p className={styles.eyebrow}>Project board</p>
        <h1>{state.project.name}</h1>
        <p>Move work deliberately through the team’s current delivery flow.</p>
      </div>
      <div className={styles.headerActions}>
        <CollaborationStatus connection={collaboration.connection} members={collaboration.members} />
        <span className={styles.contextBadge}>{activeSprint ? `${activeSprint.name} · Active` : "No active sprint"}</span>
        {isOwner ? <button className={styles.primaryButton} type="button" onClick={() => startCreate()}>Create task</button> : <span className={styles.accessBadge}>View only</span>}
      </div>
    </header>

    {formMode ? <TaskForm mode={formMode.type} form={form} setForm={setForm} errors={formErrors} requestError={formError} pending={formPending} sprints={state.sprints} members={state.members} onSubmit={saveTask} onCancel={() => setFormMode(null)} /> : null}

    {mutationError ? <div className={styles.errorNotice} role="alert"><span>{mutationError}</span><button type="button" onClick={() => setMutationError("")}>Dismiss</button></div> : null}
    {refreshing ? <p className={styles.refreshing} role="status">Refreshing authoritative board order…</p> : null}
    {!state.tasks.length ? <div className={styles.emptyBoard}><span aria-hidden="true">◇</span><div><h2>The board is ready for its first task</h2><p>{isOwner ? "Create a task and Syncora will keep its status and position in Laravel." : "The workspace owner has not added project tasks yet."}</p></div></div> : null}

    <DndContext
      sensors={sensors}
      collisionDetection={closestCorners}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragCancel={resetDrag}
      onDragEnd={handleDragEnd}
    >
      <section className={styles.board} aria-label={`${state.project.name} Kanban board`} aria-busy={movingId !== null || refreshing}>
        {columns.map((column, columnIndex) => <BoardColumn
          key={column.status}
          column={column}
          columnIndex={columnIndex}
          tasks={grouped[column.status]}
          grouped={grouped}
          isOwner={isOwner}
          interactionLocked={movingId !== null || refreshing}
          movingId={movingId}
          activeTask={activeTask}
          dragTarget={dragTarget}
          taskBaseHref={`/${workspace.slug}/projects/${projectSlug}/tasks`}
          onCreate={() => startCreate(column.status)}
          onEdit={startEdit}
          onMove={moveTask}
        />)}
      </section>
      <DragOverlay dropAnimation={{ duration: 150, easing: "ease-out" }}>
        {activeTask ? <div className={styles.dragOverlay}><TaskCard task={activeTask} overlay /></div> : null}
      </DragOverlay>
    </DndContext>
  </>;
}

function CollaborationStatus({ connection, members }) {
  const label = connection === "connected"
    ? "Live"
    : connection === "connecting" || connection === "reconnecting"
      ? "Connecting"
      : "Offline";

  return <div className={styles.collaboration} aria-label={`${members.length} collaborators online. Real-time status: ${label}.`}>
    <span className={`${styles.connectionStatus} ${styles[`connection${label}`]}`}>
      <span aria-hidden="true" />{label}
    </span>
    <div className={styles.onlineMembers}>
      {members.length ? <div className={styles.onlineList} aria-hidden="true">
        {members.slice(0, 4).map((member) => <UserAvatar className={styles.onlineAvatar} name={member.name} src={member.avatar_url} key={member.id} />)}
        {members.length > 4 ? <span className={styles.onlineMore}>+{members.length - 4}</span> : null}
      </div> : null}
      <strong>{members.length ? `${members.length} online` : "Waiting for collaborators"}</strong>
    </div>
  </div>;
}

function BoardColumn({ column, columnIndex, tasks, grouped, isOwner, interactionLocked, movingId, activeTask, dragTarget, taskBaseHref, onCreate, onEdit, onMove }) {
  const { setNodeRef, isOver } = useDroppable({
    id: `column:${column.status}`,
    data: { type: "column", status: column.status },
    disabled: !isOwner || interactionLocked,
  });
  const acceptsDrag = activeTask && dragTarget?.status === column.status;

  return <section
    ref={setNodeRef}
    className={`${styles.column} ${styles[column.status]} ${acceptsDrag || (activeTask && isOver) ? styles.columnTarget : ""}`}
    aria-labelledby={`column-${column.status}`}
  >
    <header className={styles.columnHeader}>
      <div><span className={styles.statusDot} aria-hidden="true" /><h2 id={`column-${column.status}`}>{column.label}</h2></div>
      <span className={styles.columnCount}>{tasks.length}</span>
      <p>{column.note}</p>
    </header>
    <SortableContext items={tasks.map((task) => `task:${task.id}`)} strategy={verticalListSortingStrategy}>
      <div className={styles.taskList}>
        {!tasks.length ? <p className={`${styles.emptyColumn} ${acceptsDrag ? styles.emptyColumnTarget : ""}`}>No tasks in {column.label.toLowerCase()}.</p> : null}
        {tasks.map((task, taskIndex) => <SortableTaskCard
          key={task.id}
          task={task}
          isOwner={isOwner}
          interactionLocked={interactionLocked}
          pending={movingId === task.id}
          showInsertion={activeTask?.id !== task.id && dragTarget?.taskId === task.id}
          detailHref={`${taskBaseHref}/${task.id}`}
          canMoveUp={taskIndex > 0}
          canMoveDown={taskIndex < tasks.length - 1}
          canMoveLeft={columnIndex > 0}
          canMoveRight={columnIndex < columns.length - 1}
          onEdit={() => onEdit(task)}
          onMoveUp={() => onMove(task, task.status, taskIndex - 1)}
          onMoveDown={() => onMove(task, task.status, taskIndex + 1)}
          onMoveLeft={() => onMove(task, columns[columnIndex - 1].status, grouped[columns[columnIndex - 1].status].length)}
          onMoveRight={() => onMove(task, columns[columnIndex + 1].status, grouped[columns[columnIndex + 1].status].length)}
        />)}
      </div>
    </SortableContext>
    <ColumnEndDropZone status={column.status} active={Boolean(activeTask)} targeted={Boolean(activeTask && dragTarget?.status === column.status && dragTarget?.atEnd)} />
    {isOwner ? <button className={styles.addToColumn} type="button" onClick={onCreate} disabled={interactionLocked}>+ Add task to {column.label}</button> : null}
  </section>;
}

function ColumnEndDropZone({ status, active, targeted }) {
  const { setNodeRef, isOver } = useDroppable({
    id: `column-end:${status}`,
    data: { type: "column-end", status },
    disabled: !active,
  });

  return <div ref={setNodeRef} className={`${styles.endDropZone} ${active ? styles.endDropZoneActive : ""} ${targeted || isOver ? styles.endDropZoneTarget : ""}`} aria-hidden="true"><span>Drop at end</span></div>;
}

function SortableTaskCard(props) {
  const { task, isOwner, interactionLocked, showInsertion } = props;
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: `task:${task.id}`,
    data: { type: "task", task },
    disabled: !isOwner || interactionLocked,
  });
  const style = {
    transform: transform ? `translate3d(${Math.round(transform.x)}px, ${Math.round(transform.y)}px, 0) scaleX(${transform.scaleX ?? 1}) scaleY(${transform.scaleY ?? 1})` : undefined,
    transition,
  };

  return <div ref={setNodeRef} style={style} className={`${styles.sortableCard} ${isDragging ? styles.sortableDragging : ""} ${showInsertion ? styles.insertionTarget : ""}`}>
    <TaskCard {...props} dragHandleProps={isOwner ? { ...attributes, ...listeners } : null} />
  </div>;
}

function TaskCard({ task, isOwner = false, interactionLocked = false, pending = false, overlay = false, dragHandleProps = null, detailHref = null, canMoveUp = false, canMoveDown = false, canMoveLeft = false, canMoveRight = false, onEdit, onMoveUp, onMoveDown, onMoveLeft, onMoveRight }) {
  const priorityLabel = task.priority.charAt(0).toUpperCase() + task.priority.slice(1);
  return <article className={`${styles.taskCard} ${pending ? styles.taskPending : ""} ${overlay ? styles.taskOverlayCard : ""}`}>
    <div className={styles.taskTopline}>
      <span className={`${styles.priority} ${styles[`priority${priorityLabel}`]}`}>{priorityLabel}</span>
      {isOwner ? <span className={styles.cardActions}><button className={styles.dragHandle} type="button" {...dragHandleProps} disabled={interactionLocked} aria-label={`Drag ${task.title}`}>⠿</button><button className={styles.editButton} type="button" onClick={onEdit} disabled={interactionLocked}>Edit</button></span> : null}
    </div>
    <h3>{detailHref ? <Link className={styles.taskTitleLink} href={detailHref}>{task.title}</Link> : task.title}</h3>
    {task.description ? <p className={styles.taskDescription}>{task.description}</p> : null}
    <div className={styles.taskContext}>
      <span className={styles.sprintLabel}>{task.sprint ? task.sprint.name : "No sprint"}</span>
      {task.assignee ? <span className={styles.assignee}><UserAvatar className={styles.miniAvatar} name={task.assignee.name} src={task.assignee.avatar_url} /><span>{task.assignee.name}</span></span> : <span className={styles.unassigned}>Unassigned</span>}
    </div>
    {isOwner ? <div className={styles.moveControls} aria-label={`Move ${task.title}`}>
      <button type="button" onClick={onMoveLeft} disabled={!canMoveLeft || pending || interactionLocked} aria-label={`Move ${task.title} to previous column`}>←</button>
      <button type="button" onClick={onMoveUp} disabled={!canMoveUp || pending || interactionLocked} aria-label={`Move ${task.title} up`}>↑</button>
      <span>{pending ? "Saving…" : "Move"}</span>
      <button type="button" onClick={onMoveDown} disabled={!canMoveDown || pending || interactionLocked} aria-label={`Move ${task.title} down`}>↓</button>
      <button type="button" onClick={onMoveRight} disabled={!canMoveRight || pending || interactionLocked} aria-label={`Move ${task.title} to next column`}>→</button>
    </div> : null}
  </article>;
}

function TaskForm({ mode, form, setForm, errors, requestError, pending, sprints, members, onSubmit, onCancel }) {
  return <section className={styles.taskFormPanel} aria-labelledby="task-form-title">
    <div className={styles.formIntro}>
      <p className={styles.eyebrow}>{mode === "create" ? "New work item" : "Task details"}</p>
      <h2 id="task-form-title">{mode === "create" ? "Create a clear task" : "Refine this task"}</h2>
      <p>Keep enough context to make the card useful without overloading the board.</p>
    </div>
    <form className={styles.form} onSubmit={onSubmit} noValidate>
      <Field label="Title" error={errors.title?.[0]}><input id="task-title" value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} maxLength={160} required autoFocus /></Field>
      <Field label="Description" error={errors.description?.[0]} optional><textarea id="task-description" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} maxLength={5000} rows={4} /></Field>
      <div className={styles.formGrid}>
        <Field label="Status" error={errors.status?.[0]}><select id="task-status" value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>{columns.map((column) => <option value={column.status} key={column.status}>{column.label}</option>)}</select></Field>
        <Field label="Priority" error={errors.priority?.[0]}><select id="task-priority" value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })}><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></Field>
        <Field label="Sprint" error={errors.sprint_id?.[0]} optional><select id="task-sprint" value={form.sprint_id} onChange={(event) => setForm({ ...form, sprint_id: event.target.value })}><option value="">No sprint</option>{sprints.map((sprint) => <option value={sprint.id} key={sprint.id}>{sprint.name} · {sprint.status}</option>)}</select></Field>
        <Field label="Assignee" error={errors.assignee_id?.[0]} optional><select id="task-assignee" value={form.assignee_id} onChange={(event) => setForm({ ...form, assignee_id: event.target.value })}><option value="">Unassigned</option>{members.map((member) => <option value={member.id} key={member.id}>{member.name}</option>)}</select></Field>
      </div>
      {!members.length ? <p className={styles.fieldHint}>No workspace members are available for assignment.</p> : null}
      {requestError ? <p className={styles.formError} role="alert">{requestError}</p> : null}
      <div className={styles.formActions}><button className={styles.secondaryButton} type="button" onClick={onCancel} disabled={pending}>Cancel</button><button className={styles.primaryButton} type="submit" disabled={pending}>{pending ? "Saving task…" : mode === "create" ? "Create task" : "Save task"}</button></div>
    </form>
  </section>;
}

function Field({ label, error, optional = false, children }) {
  return <div className={styles.field}><div className={styles.labelRow}><label htmlFor={children.props.id}>{label}</label>{optional ? <span>Optional</span> : null}</div>{children}{error ? <p className={styles.fieldError} role="alert">{error}</p> : null}</div>;
}

function BoardState({ label }) {
  return <div className={styles.statePanel} role="status"><span className={styles.spinner} aria-hidden="true" />{label}</div>;
}

function BoardError({ code, message, retry, workspaceSlug }) {
  const notFound = code === 404;
  return <div className={styles.errorPanel} role="alert"><span aria-hidden="true">!</span><div><h1>{notFound ? "Project board not found" : code === 403 ? "Board access denied" : "Board unavailable"}</h1><p>{notFound ? "This project does not belong to the current workspace, or you no longer have access." : message}</p><div>{!notFound ? <button className={styles.secondaryButton} type="button" onClick={retry}>Try again</button> : null}<Link className={styles.secondaryButton} href={`/${workspaceSlug}/projects`}>Back to projects</Link></div></div></div>;
}
