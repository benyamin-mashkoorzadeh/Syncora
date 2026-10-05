"use client";

import UserAvatar from "@/components/user-avatar";
import { notificationApi } from "@/lib/notification-api";
import { useUserNotifications } from "@/lib/use-user-notifications";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import styles from "./notification-center.module.css";

function mergeNotifications(current, incoming) {
  const notifications = new Map(current.map((notification) => [String(notification.id), notification]));
  incoming.forEach((notification) => notifications.set(String(notification.id), notification));

  return Array.from(notifications.values()).sort((first, second) => Number(second.id) - Number(first.id));
}

function notificationText(notification) {
  return notification.type === "task.assigned"
    ? `${notification.actor.name} assigned you to`
    : `${notification.actor.name} commented on`;
}

function notificationHref(notification) {
  return `/${notification.project.workspace_slug}/projects/${notification.project.slug}/tasks/${notification.task.id}`;
}

function formatTime(value) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

export default function NotificationCenter({ user }) {
  const router = useRouter();
  const rootRef = useRef(null);
  const pagesLoadedRef = useRef(1);
  const refreshTimerRef = useRef(null);
  const [open, setOpen] = useState(false);
  const [state, setState] = useState({ loading: true, error: "", notifications: [], unreadCount: 0, page: 1, lastPage: 1 });
  const [marking, setMarking] = useState(null);
  const [markingAll, setMarkingAll] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);

  const load = useCallback(async ({ signal, pages = 1 } = {}) => {
    try {
      const payloads = await Promise.all(
        Array.from({ length: pages }, (_, index) => notificationApi.list({ page: index + 1, signal })),
      );
      const first = payloads[0];
      pagesLoadedRef.current = pages;
      setState({
        loading: false,
        error: "",
        notifications: mergeNotifications([], payloads.flatMap((payload) => payload.data)),
        unreadCount: first.unread_count,
        page: pages,
        lastPage: first.meta.last_page,
      });
    } catch (error) {
      if (error.name !== "AbortError") {
        setState((current) => ({ ...current, loading: false, error: error.message }));
      }
    }
  }, []);

  const reconcile = useCallback(() => {
    window.clearTimeout(refreshTimerRef.current);
    refreshTimerRef.current = window.setTimeout(() => load({ pages: pagesLoadedRef.current }), 120);
  }, [load]);

  const realtime = useUserNotifications({
    userId: user.id,
    onNotification: reconcile,
    onReconnect: reconcile,
  });

  useEffect(() => {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => load({ signal: controller.signal }), 0);
    return () => {
      window.clearTimeout(timeout);
      window.clearTimeout(refreshTimerRef.current);
      controller.abort();
    };
  }, [load]);

  useEffect(() => {
    if (!open) return;

    function handlePointer(event) {
      if (!rootRef.current?.contains(event.target)) setOpen(false);
    }

    function handleKey(event) {
      if (event.key === "Escape") setOpen(false);
    }

    document.addEventListener("pointerdown", handlePointer);
    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("pointerdown", handlePointer);
      document.removeEventListener("keydown", handleKey);
    };
  }, [open]);

  async function loadMore() {
    if (loadingMore || state.page >= state.lastPage) return;
    const nextPage = state.page + 1;
    setLoadingMore(true);
    setState((current) => ({ ...current, error: "" }));
    try {
      const payload = await notificationApi.list({ page: nextPage });
      pagesLoadedRef.current = nextPage;
      setState((current) => ({
        ...current,
        notifications: mergeNotifications(current.notifications, payload.data),
        unreadCount: payload.unread_count,
        page: nextPage,
        lastPage: payload.meta.last_page,
      }));
    } catch (error) {
      setState((current) => ({ ...current, error: error.message }));
    } finally {
      setLoadingMore(false);
    }
  }

  async function markRead(notification, { navigate = false } = {}) {
    if (marking) return;
    setMarking(notification.id);
    setState((current) => ({ ...current, error: "" }));
    try {
      if (!notification.is_read) {
        const payload = await notificationApi.markRead(notification.id);
        setState((current) => ({
          ...current,
          notifications: current.notifications.map((item) => item.id === payload.data.id ? payload.data : item),
          unreadCount: payload.unread_count,
        }));
      }
      if (navigate) {
        setOpen(false);
        router.push(notificationHref(notification));
      }
    } catch (error) {
      setState((current) => ({ ...current, error: error.message }));
    } finally {
      setMarking(null);
    }
  }

  async function markAllRead() {
    if (markingAll || !state.unreadCount) return;
    setMarkingAll(true);
    setState((current) => ({ ...current, error: "" }));
    try {
      await notificationApi.markAllRead();
      const readAt = new Date().toISOString();
      setState((current) => ({
        ...current,
        unreadCount: 0,
        notifications: current.notifications.map((notification) => ({ ...notification, is_read: true, read_at: notification.read_at ?? readAt })),
      }));
    } catch (error) {
      setState((current) => ({ ...current, error: error.message }));
    } finally {
      setMarkingAll(false);
    }
  }

  const connectionLabel = realtime.connection === "connected"
    ? "Live"
    : realtime.connection === "connecting" || realtime.connection === "reconnecting"
      ? "Connecting"
      : "Offline";

  return <div className={styles.notificationCenter} ref={rootRef}>
    <button className={styles.bellButton} type="button" onClick={() => setOpen((current) => !current)} aria-label={`Notifications${state.unreadCount ? `, ${state.unreadCount} unread` : ""}`} aria-haspopup="dialog" aria-expanded={open}>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
      {state.unreadCount ? <span className={styles.unreadBadge}>{state.unreadCount > 99 ? "99+" : state.unreadCount}</span> : null}
    </button>

    {open ? <section className={styles.panel} role="dialog" aria-label="Notifications">
      <header className={styles.panelHeader}>
        <div><h2>Notifications</h2><span className={`${styles.connection} ${styles[`connection${connectionLabel}`]}`}><span aria-hidden="true" />{connectionLabel}</span></div>
        <button type="button" onClick={markAllRead} disabled={markingAll || !state.unreadCount}>{markingAll ? "Marking…" : "Mark all read"}</button>
      </header>

      <div className={styles.panelBody}>
        {state.loading ? <PanelState message="Loading notifications…" /> : null}
        {!state.loading && state.error ? <PanelState message={state.error} error retry={() => load({ pages: pagesLoadedRef.current })} /> : null}
        {!state.loading && !state.error && !state.notifications.length ? <PanelState title="You’re all caught up" message="Task assignments and relevant comments will appear here." /> : null}

        {!state.loading && state.notifications.length ? <div className={styles.notificationList}>
          {state.notifications.map((notification) => <article className={`${styles.notification} ${notification.is_read ? styles.read : styles.unread}`} key={notification.id}>
            <button className={styles.notificationLink} type="button" onClick={() => markRead(notification, { navigate: true })} disabled={marking === notification.id}>
              <UserAvatar className={styles.actorAvatar} name={notification.actor.name} src={notification.actor.avatar_url} />
              <span className={styles.notificationContent}>
                <span><strong>{notificationText(notification)}</strong> <b>{notification.task.title}</b></span>
                <span className={styles.projectName}>{notification.project.name}</span>
                <time dateTime={notification.created_at}>{formatTime(notification.created_at)}</time>
              </span>
              {!notification.is_read ? <span className={styles.unreadDot} aria-label="Unread" /> : null}
            </button>
            {!notification.is_read ? <button className={styles.markReadButton} type="button" onClick={() => markRead(notification)} disabled={marking === notification.id}>Mark read</button> : null}
          </article>)}
          {state.page < state.lastPage ? <button className={styles.loadMore} type="button" onClick={loadMore} disabled={loadingMore}>{loadingMore ? "Loading…" : "Load earlier notifications"}</button> : null}
        </div> : null}
      </div>
    </section> : null}
  </div>;
}

function PanelState({ title = null, message, error = false, retry = null }) {
  return <div className={`${styles.panelState} ${error ? styles.errorState : ""}`} role={error ? "alert" : "status"}>
    <span aria-hidden="true">{error ? "!" : title ? "✓" : "…"}</span>
    <div>{title ? <h3>{title}</h3> : null}<p>{message}</p>{retry ? <button type="button" onClick={retry}>Try again</button> : null}</div>
  </div>;
}
