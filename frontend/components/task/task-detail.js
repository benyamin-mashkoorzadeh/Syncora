"use client";

import UserAvatar from "@/components/user-avatar";
import { taskApi } from "@/lib/task-api";
import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import styles from "./task-detail.module.css";

const statusLabels = { todo: "To Do", in_progress: "In Progress", done: "Done" };

export default function TaskDetail({ workspace, projectSlug, taskId, currentUser }) {
  const [state, setState] = useState({ loading: true, error: "", code: 0, task: null, comments: [], commentMeta: null, activities: [], activityMeta: null });
  const [body, setBody] = useState("");
  const [commentError, setCommentError] = useState("");
  const [commentMessage, setCommentMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [loadingMore, setLoadingMore] = useState("");
  const [editingCommentId, setEditingCommentId] = useState(null);
  const [editBody, setEditBody] = useState("");
  const [commentMutation, setCommentMutation] = useState(null);
  const [commentActionError, setCommentActionError] = useState({ id: null, message: "" });

  const load = useCallback(async (signal) => {
    try {
      const [taskPayload, commentPayload, activityPayload] = await Promise.all([
        taskApi.show(workspace.slug, projectSlug, taskId, { signal }),
        taskApi.comments(workspace.slug, projectSlug, taskId, { signal }),
        taskApi.activities(workspace.slug, projectSlug, taskId, { signal }),
      ]);
      setState({
        loading: false,
        error: "",
        code: 0,
        task: taskPayload.data,
        comments: commentPayload.data,
        commentMeta: commentPayload.meta,
        activities: activityPayload.data,
        activityMeta: activityPayload.meta,
      });
    } catch (error) {
      if (error.name !== "AbortError") {
        setState((current) => ({ ...current, loading: false, error: error.message, code: error.status ?? 0 }));
      }
    }
  }, [projectSlug, taskId, workspace.slug]);

  useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load(controller.signal), 0);
    return () => { window.clearTimeout(timeout); controller.abort(); };
  }, [load]);

  async function submitComment(event) {
    event.preventDefault();
    setSubmitting(true);
    setCommentError("");
    setCommentMessage("");
    try {
      await taskApi.addComment(workspace.slug, projectSlug, taskId, body);
      const [commentPayload, activityPayload] = await Promise.all([
        taskApi.comments(workspace.slug, projectSlug, taskId),
        taskApi.activities(workspace.slug, projectSlug, taskId),
      ]);
      setState((current) => ({ ...current, comments: commentPayload.data, commentMeta: commentPayload.meta, activities: activityPayload.data, activityMeta: activityPayload.meta }));
      setBody("");
      setCommentMessage("Comment added.");
    } catch (error) {
      setCommentError(error.errors?.body?.[0] ?? error.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function refreshCollaboration() {
    const [commentPayload, activityPayload] = await Promise.all([
      taskApi.comments(workspace.slug, projectSlug, taskId),
      taskApi.activities(workspace.slug, projectSlug, taskId),
    ]);
    setState((current) => ({ ...current, comments: commentPayload.data, commentMeta: commentPayload.meta, activities: activityPayload.data, activityMeta: activityPayload.meta }));
  }

  function startEditing(comment) {
    setEditingCommentId(comment.id);
    setEditBody(comment.body);
    setCommentActionError({ id: null, message: "" });
    setCommentMessage("");
  }

  async function saveComment(event, comment) {
    event.preventDefault();
    setCommentMutation({ id: comment.id, type: "edit" });
    setCommentActionError({ id: null, message: "" });
    setCommentMessage("");
    try {
      await taskApi.updateComment(workspace.slug, projectSlug, taskId, comment.id, editBody);
      await refreshCollaboration();
      setEditingCommentId(null);
      setEditBody("");
      setCommentMessage("Comment updated.");
    } catch (error) {
      setCommentActionError({ id: comment.id, message: error.errors?.body?.[0] ?? error.message });
    } finally {
      setCommentMutation(null);
    }
  }

  async function deleteComment(comment) {
    if (!window.confirm("Delete this comment? This cannot be undone.")) return;
    setCommentMutation({ id: comment.id, type: "delete" });
    setCommentActionError({ id: null, message: "" });
    setCommentMessage("");
    try {
      await taskApi.deleteComment(workspace.slug, projectSlug, taskId, comment.id);
      await refreshCollaboration();
      if (editingCommentId === comment.id) setEditingCommentId(null);
      setCommentMessage("Comment deleted.");
    } catch (error) {
      setCommentActionError({ id: comment.id, message: error.message });
    } finally {
      setCommentMutation(null);
    }
  }

  async function loadMore(type) {
    const meta = type === "comments" ? state.commentMeta : state.activityMeta;
    if (!meta || meta.current_page >= meta.last_page) return;
    setLoadingMore(type);
    setCommentError("");
    try {
      const payload = type === "comments"
        ? await taskApi.comments(workspace.slug, projectSlug, taskId, { page: meta.current_page + 1 })
        : await taskApi.activities(workspace.slug, projectSlug, taskId, { page: meta.current_page + 1 });
      setState((current) => type === "comments"
        ? { ...current, comments: [...current.comments, ...payload.data], commentMeta: payload.meta }
        : { ...current, activities: [...current.activities, ...payload.data], activityMeta: payload.meta });
    } catch (error) {
      setCommentError(error.message);
    } finally {
      setLoadingMore("");
    }
  }

  if (state.loading) return <StatePanel label="Loading task details…" />;
  if (state.error) return <ErrorPanel code={state.code} message={state.error} retry={() => load()} boardHref={boardHref(workspace.slug, projectSlug)} />;

  const task = state.task;
  return <>
    <Link className={styles.backLink} href={boardHref(workspace.slug, projectSlug)}>← Back to board</Link>
    <header className={styles.taskHeader}>
      <div className={styles.headerCopy}>
        <p className={styles.eyebrow}>{task.project?.name ?? "Project task"}</p>
        <h1>{task.title}</h1>
        <p>{task.description || "No description has been added yet."}</p>
      </div>
      <div className={styles.taskMeta} aria-label="Task details">
        <Meta label="Status"><span className={`${styles.badge} ${styles[`status_${task.status}`]}`}>{statusLabels[task.status]}</span></Meta>
        <Meta label="Priority"><span className={`${styles.badge} ${styles[`priority_${task.priority}`]}`}>{capitalize(task.priority)}</span></Meta>
        <Meta label="Assignee">{task.assignee ? <span className={styles.person}><UserAvatar className={styles.avatar} name={task.assignee.name} src={task.assignee.avatar_url} />{task.assignee.name}</span> : <span className={styles.muted}>Unassigned</span>}</Meta>
        <Meta label="Sprint"><span className={task.sprint ? "" : styles.muted}>{task.sprint?.name ?? "No sprint"}</span></Meta>
      </div>
    </header>

    <div className={styles.collaborationGrid}>
      <section className={styles.commentsSection} aria-labelledby="comments-title">
        <SectionHeading eyebrow="Conversation" title="Comments" count={state.commentMeta?.total ?? state.comments.length} id="comments-title" />
        <form className={styles.commentForm} onSubmit={submitComment} noValidate>
          <label htmlFor="task-comment">Add context for your team</label>
          <textarea id="task-comment" value={body} onChange={(event) => setBody(event.target.value)} rows={4} maxLength={5000} placeholder="Write a focused comment…" disabled={submitting} />
          <div className={styles.formFooter}>
            <div>{commentError ? <p className={styles.errorText} role="alert">{commentError}</p> : null}{commentMessage ? <p className={styles.successText} role="status">{commentMessage}</p> : null}</div>
            <button className={styles.primaryButton} type="submit" disabled={submitting}>{submitting ? "Adding comment…" : "Add comment"}</button>
          </div>
        </form>

        {!state.comments.length ? <EmptyState title="No comments yet" body="Add the first note, decision, or piece of context for this task." /> : null}
        {state.comments.length ? <div className={styles.commentList}>{state.comments.map((comment) => <article className={styles.comment} key={comment.id}>
          <UserAvatar className={styles.commentAvatar} name={comment.author.name} src={comment.author.avatar_url} />
          <div>
            <div className={styles.commentByline}><span><strong>{comment.author.name}</strong>{comment.updated_at !== comment.created_at ? <em>(edited)</em> : null}</span><time dateTime={comment.created_at}>{formatDate(comment.created_at)}</time></div>
            {editingCommentId === comment.id ? <form className={styles.editCommentForm} onSubmit={(event) => saveComment(event, comment)} noValidate>
              <label className={styles.srOnly} htmlFor={`edit-comment-${comment.id}`}>Edit comment</label>
              <textarea id={`edit-comment-${comment.id}`} value={editBody} onChange={(event) => setEditBody(event.target.value)} rows={3} maxLength={5000} disabled={commentMutation?.id === comment.id} autoFocus />
              {commentActionError.id === comment.id ? <p className={styles.errorText} role="alert">{commentActionError.message}</p> : null}
              <div className={styles.commentEditActions}><button type="button" onClick={() => { setEditingCommentId(null); setCommentActionError({ id: null, message: "" }); }} disabled={commentMutation?.id === comment.id}>Cancel</button><button className={styles.saveEdit} type="submit" disabled={commentMutation?.id === comment.id}>{commentMutation?.type === "edit" && commentMutation.id === comment.id ? "Saving…" : "Save"}</button></div>
            </form> : <p>{comment.body}</p>}
            {comment.author.id === currentUser.id && editingCommentId !== comment.id ? <div className={styles.commentActions}><button type="button" onClick={() => startEditing(comment)} disabled={commentMutation?.id === comment.id}>Edit</button><button className={styles.deleteAction} type="button" onClick={() => deleteComment(comment)} disabled={commentMutation?.id === comment.id}>{commentMutation?.type === "delete" && commentMutation.id === comment.id ? "Deleting…" : "Delete"}</button></div> : null}
            {commentActionError.id === comment.id && editingCommentId !== comment.id ? <p className={styles.errorText} role="alert">{commentActionError.message}</p> : null}
          </div>
        </article>)}</div> : null}
        <LoadMore meta={state.commentMeta} pending={loadingMore === "comments"} onClick={() => loadMore("comments")} label="older comments" />
      </section>

      <aside className={styles.activitySection} aria-labelledby="activity-title">
        <SectionHeading eyebrow="History" title="Activity" count={state.activityMeta?.total ?? state.activities.length} id="activity-title" />
        {!state.activities.length ? <EmptyState title="No activity yet" body="Meaningful task changes will appear here." compact /> : null}
        {state.activities.length ? <ol className={styles.timeline}>{state.activities.map((activity) => <li key={activity.id}>
          {activity.actor ? <UserAvatar className={styles.activityAvatar} name={activity.actor.name} src={activity.actor.avatar_url} /> : <span className={styles.timelineDot} aria-hidden="true" />}
          <div><p>{activitySentence(activity)}</p><time dateTime={activity.created_at}>{formatDate(activity.created_at)}</time></div>
        </li>)}</ol> : null}
        <LoadMore meta={state.activityMeta} pending={loadingMore === "activities"} onClick={() => loadMore("activities")} label="older activity" />
      </aside>
    </div>
  </>;
}

function Meta({ label, children }) {
  return <div><span>{label}</span><strong>{children}</strong></div>;
}

function SectionHeading({ eyebrow, title, count, id }) {
  return <div className={styles.sectionHeading}><div><p className={styles.eyebrow}>{eyebrow}</p><h2 id={id}>{title}</h2></div><span className={styles.count}>{count}</span></div>;
}

function EmptyState({ title, body, compact = false }) {
  return <div className={`${styles.emptyState} ${compact ? styles.compactEmpty : ""}`}><span aria-hidden="true">◇</span><h3>{title}</h3><p>{body}</p></div>;
}

function LoadMore({ meta, pending, onClick, label }) {
  if (!meta || meta.current_page >= meta.last_page) return null;
  return <button className={styles.loadMore} type="button" onClick={onClick} disabled={pending}>{pending ? "Loading…" : `Load ${label}`}</button>;
}

function StatePanel({ label }) {
  return <div className={styles.statePanel} role="status"><span className={styles.spinner} aria-hidden="true" />{label}</div>;
}

function ErrorPanel({ code, message, retry, boardHref: href }) {
  const missing = code === 404;
  return <div className={styles.errorPanel} role="alert"><span aria-hidden="true">!</span><div><h1>{missing ? "Task not found" : code === 403 ? "Task access denied" : "Task unavailable"}</h1><p>{missing ? "This task does not belong to the current project, or you no longer have access." : message}</p><div>{!missing ? <button type="button" onClick={retry}>Try again</button> : null}<Link href={href}>Back to board</Link></div></div></div>;
}

function activitySentence(activity) {
  const actor = activity.actor?.name ?? "A former member";
  const from = activity.metadata?.from;
  const to = activity.metadata?.to;
  switch (activity.action) {
    case "task.created": return `${actor} created this task.`;
    case "task.title_changed": return `${actor} renamed this task from “${from?.value ?? "Untitled"}” to “${to?.value ?? "Untitled"}”.`;
    case "task.description_changed": return `${actor} updated the task description.`;
    case "task.status_changed": return `${actor} moved this task from ${from?.label ?? "its previous status"} to ${to?.label ?? "a new status"}.`;
    case "task.priority_changed": return `${actor} changed priority from ${from?.label ?? "its previous priority"} to ${to?.label ?? "a new priority"}.`;
    case "task.assignee_changed": return to?.id ? (from?.id ? `${actor} changed the assignee from ${from.label} to ${to.label}.` : `${actor} assigned this task to ${to.label}.`) : `${actor} unassigned this task from ${from?.label ?? "its assignee"}.`;
    case "task.sprint_changed": return to?.id ? (from?.id ? `${actor} moved this task from ${from.label} to ${to.label}.` : `${actor} moved this task to ${to.label}.`) : `${actor} removed this task from ${from?.label ?? "its sprint"}.`;
    case "comment.added": return `${actor} added a comment.`;
    case "comment.edited": return `${actor} edited a comment.`;
    case "comment.deleted": return `${actor} deleted a comment.`;
    default: return `${actor} updated this task.`;
  }
}

function formatDate(value) {
  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function capitalize(value) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function boardHref(workspaceSlug, projectSlug) {
  return `/${workspaceSlug}/projects/${projectSlug}/board`;
}
