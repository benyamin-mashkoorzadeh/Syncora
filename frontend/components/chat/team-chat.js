"use client";

import UserAvatar from "@/components/user-avatar";
import { chatApi } from "@/lib/chat-api";
import { projectApi } from "@/lib/project-api";
import { useProjectCollaboration } from "@/lib/use-project-collaboration";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import styles from "./team-chat.module.css";

function mergeMessages(current, incoming) {
  const messages = new Map(current.map((message) => [String(message.id), message]));
  incoming.forEach((message) => messages.set(String(message.id), message));

  return Array.from(messages.values()).sort((first, second) => Number(first.id) - Number(second.id));
}

function formatTime(value) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

export default function TeamChat({ workspace, projectSlug, currentUser }) {
  const messageListRef = useRef(null);
  const messageEndRef = useRef(null);
  const inputRef = useRef(null);
  const oldestPageRef = useRef(1);
  const knownMessageIdsRef = useRef(new Set());
  const [state, setState] = useState({ loading: true, error: "", code: 0, project: null, messages: [] });
  const [history, setHistory] = useState({ loading: false, error: "", oldestPage: 1, lastPage: 1, total: 0 });
  const [body, setBody] = useState("");
  const [sending, setSending] = useState(false);
  const [sendError, setSendError] = useState("");
  const [reconciling, setReconciling] = useState(false);

  const scrollToLatest = useCallback((behavior = "smooth") => {
    messageEndRef.current?.scrollIntoView({ block: "end", behavior });
  }, []);

  const appendMessage = useCallback((message, forceScroll = false) => {
    const list = messageListRef.current;
    const nearLatest = list ? list.scrollHeight - list.scrollTop - list.clientHeight < 140 : true;
    const isNew = !knownMessageIdsRef.current.has(String(message.id));
    knownMessageIdsRef.current.add(String(message.id));
    setState((current) => ({ ...current, messages: mergeMessages(current.messages, [message]) }));
    if (isNew) {
      setHistory((current) => {
        const total = current.total + 1;
        return { ...current, total, lastPage: Math.max(current.lastPage, Math.ceil(total / 30)) };
      });
    }
    if (forceScroll || nearLatest) {
      window.requestAnimationFrame(() => scrollToLatest());
    }
  }, [scrollToLatest]);

  const load = useCallback(async (signal) => {
    try {
      const [projectPayload, messagePayload] = await Promise.all([
        projectApi.show(workspace.slug, projectSlug, { signal }),
        chatApi.list(workspace.slug, projectSlug, { signal }),
      ]);
      oldestPageRef.current = 1;
      knownMessageIdsRef.current = new Set(messagePayload.data.map((message) => String(message.id)));
      setState({
        loading: false,
        error: "",
        code: 0,
        project: projectPayload.data,
        messages: mergeMessages([], messagePayload.data),
      });
      setHistory({ loading: false, error: "", oldestPage: 1, lastPage: messagePayload.meta.last_page, total: messagePayload.meta.total });
      window.requestAnimationFrame(() => scrollToLatest("auto"));
    } catch (error) {
      if (error.name !== "AbortError") {
        setState((current) => ({ ...current, loading: false, error: error.message, code: error.status ?? 0 }));
      }
    }
  }, [projectSlug, scrollToLatest, workspace.slug]);

  const reconcileHistory = useCallback(async () => {
    setReconciling(true);
    try {
      const pages = await Promise.all(
        Array.from({ length: oldestPageRef.current }, (_, index) => chatApi.list(workspace.slug, projectSlug, { page: index + 1 })),
      );
      const authoritativeMessages = pages.flatMap((payload) => payload.data);
      authoritativeMessages.forEach((message) => knownMessageIdsRef.current.add(String(message.id)));
      setState((current) => ({ ...current, messages: mergeMessages(current.messages, authoritativeMessages) }));
      setHistory((current) => ({
        ...current,
        error: "",
        lastPage: pages[0]?.meta.last_page ?? current.lastPage,
        total: pages[0]?.meta.total ?? current.total,
      }));
    } catch {
      setHistory((current) => ({ ...current, error: "Chat history could not be reconciled. Retry when your connection is stable." }));
    } finally {
      setReconciling(false);
    }
  }, [projectSlug, workspace.slug]);

  const collaboration = useProjectCollaboration({
    projectId: state.project?.id,
    onMessageCreated: appendMessage,
    onReconnect: reconcileHistory,
  });

  useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load(controller.signal), 0);
    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, [load]);

  async function loadEarlier() {
    if (history.loading || oldestPageRef.current >= history.lastPage) return;
    const nextPage = oldestPageRef.current + 1;
    const list = messageListRef.current;
    const previousHeight = list?.scrollHeight ?? 0;
    setHistory((current) => ({ ...current, loading: true, error: "" }));

    try {
      const payload = await chatApi.list(workspace.slug, projectSlug, { page: nextPage });
      oldestPageRef.current = nextPage;
      payload.data.forEach((message) => knownMessageIdsRef.current.add(String(message.id)));
      setState((current) => ({ ...current, messages: mergeMessages(current.messages, payload.data) }));
      setHistory({ loading: false, error: "", oldestPage: nextPage, lastPage: payload.meta.last_page, total: payload.meta.total });
      window.requestAnimationFrame(() => {
        if (list) list.scrollTop += list.scrollHeight - previousHeight;
      });
    } catch (error) {
      setHistory((current) => ({ ...current, loading: false, error: error.message }));
    }
  }

  async function sendMessage(event) {
    event.preventDefault();
    if (sending) return;
    setSending(true);
    setSendError("");

    try {
      const payload = await chatApi.send(workspace.slug, projectSlug, body);
      appendMessage(payload.data, true);
      setBody("");
      inputRef.current?.focus();
    } catch (error) {
      setSendError(error.errors?.body?.[0] ?? error.message);
    } finally {
      setSending(false);
    }
  }

  function handleComposerKeyDown(event) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();
      event.currentTarget.form?.requestSubmit();
    }
  }

  if (state.loading) return <ChatState message="Opening project chat…" />;
  if (state.error) return <ChatState title={state.code === 404 ? "Chat unavailable" : "Team chat could not load"} message={state.code === 404 ? "This project does not belong to the current workspace, or you no longer have access." : state.error} retry={state.code === 404 ? null : () => load()} />;

  const connectionLabel = collaboration.connection === "connected"
    ? "Live"
    : collaboration.connection === "connecting" || collaboration.connection === "reconnecting"
      ? "Connecting"
      : "Offline";
  const hasEarlier = history.oldestPage < history.lastPage;

  return <>
    <nav className={styles.contextNav} aria-label="Project chat navigation">
      <Link href={`/${workspace.slug}/projects/${projectSlug}`}>← Project</Link>
      <Link href={`/${workspace.slug}/projects/${projectSlug}/board`}>Open board</Link>
    </nav>
    <section className={styles.chatShell} aria-labelledby="chat-title">
      <header className={styles.chatHeader}>
        <div>
          <p className={styles.eyebrow}>Project conversation</p>
          <h1 id="chat-title">{state.project.name} chat</h1>
          <p>Keep decisions and delivery context close to the work.</p>
        </div>
        <div className={styles.liveContext} aria-label={`${collaboration.members.length} collaborators online. Real-time status: ${connectionLabel}.`}>
          <span className={`${styles.connection} ${styles[`connection${connectionLabel}`]}`}><span aria-hidden="true" />{connectionLabel}</span>
          <div className={styles.onlineMembers}>
            {collaboration.members.slice(0, 4).map((member) => <UserAvatar className={styles.onlineAvatar} name={member.name} src={member.avatar_url} key={member.id} />)}
            <span>{collaboration.members.length ? `${collaboration.members.length} online` : "No one else online"}</span>
          </div>
        </div>
      </header>

      <div className={styles.messageList} ref={messageListRef} aria-live="polite" aria-busy={reconciling}>
        <div className={styles.historyControl}>
          {hasEarlier ? <button type="button" onClick={loadEarlier} disabled={history.loading}>{history.loading ? "Loading earlier messages…" : "Load earlier messages"}</button> : state.messages.length ? <span>Beginning of this conversation</span> : null}
          {history.error ? <p role="alert">{history.error} <button type="button" onClick={hasEarlier ? loadEarlier : reconcileHistory}>Retry</button></p> : null}
          {reconciling ? <span role="status">Reconciling message history…</span> : null}
        </div>

        {!state.messages.length ? <div className={styles.emptyChat}>
          <span aria-hidden="true">↗</span>
          <h2>Start the project conversation</h2>
          <p>Share the first update, decision, or question with the team.</p>
        </div> : null}

        {state.messages.map((message) => <article className={`${styles.message} ${Number(message.sender.id) === Number(currentUser.id) ? styles.ownMessage : ""}`} key={message.id}>
          <UserAvatar className={styles.messageAvatar} name={message.sender.name} src={message.sender.avatar_url} />
          <div className={styles.messageContent}>
            <div className={styles.messageMeta}>
              <strong>{message.sender.name}</strong>
              {Number(message.sender.id) === Number(currentUser.id) ? <span className={styles.youBadge}>You</span> : null}
              <time dateTime={message.created_at}>{formatTime(message.created_at)}</time>
            </div>
            <p>{message.body}</p>
          </div>
        </article>)}
        <div ref={messageEndRef} />
      </div>

      <form className={styles.composer} onSubmit={sendMessage} noValidate>
        <div className={styles.composerField}>
          <label htmlFor="chat-message">Message the project team</label>
          <textarea id="chat-message" ref={inputRef} value={body} onChange={(event) => setBody(event.target.value)} onKeyDown={handleComposerKeyDown} rows={3} maxLength={4000} placeholder="Share an update or ask a focused question…" disabled={sending} aria-invalid={Boolean(sendError)} aria-describedby={sendError ? "chat-send-error" : "chat-message-hint"} />
          <div className={styles.composerMeta}><span id="chat-message-hint">Enter to send · Shift+Enter for a new line</span><span>{body.length}/4000</span></div>
          {sendError ? <p className={styles.sendError} id="chat-send-error" role="alert">{sendError}</p> : null}
        </div>
        <button className={styles.sendButton} type="submit" disabled={sending || !body.trim()}>{sending ? "Sending…" : "Send message"}</button>
      </form>
      {connectionLabel === "Offline" ? <p className={styles.offlineNote} role="status">Live delivery is unavailable. Messages still save through Laravel and will reconcile after reconnect.</p> : null}
    </section>
  </>;
}

function ChatState({ title = "Team chat", message, retry = null }) {
  return <section className={styles.chatState} role={title === "Team chat" ? "status" : "alert"}>
    <span aria-hidden="true">{retry ? "!" : "…"}</span>
    <div><h1>{title}</h1><p>{message}</p>{retry ? <button type="button" onClick={retry}>Try again</button> : null}</div>
  </section>;
}
