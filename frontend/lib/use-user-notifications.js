"use client";

import { getRealtimeClient } from "@/lib/realtime-client";
import { useEffect, useRef, useState } from "react";

export function useUserNotifications({ userId, onNotification, onReconnect }) {
  const notificationRef = useRef(onNotification);
  const reconnectRef = useRef(onReconnect);
  const [connection, setConnection] = useState("connecting");

  useEffect(() => {
    notificationRef.current = onNotification;
    reconnectRef.current = onReconnect;
  }, [onNotification, onReconnect]);

  useEffect(() => {
    if (!userId) return;

    let active = true;
    let echo = null;
    let removeConnectionListener = null;
    let hasConnected = false;
    let reconnectRequired = false;
    const channelName = `user.${userId}.notifications`;

    async function subscribe() {
      try {
        echo = await getRealtimeClient();
        if (!active || !echo) return;

        const handleConnection = (status) => {
          if (!active) return;
          setConnection(status);

          if (status === "connected") {
            if (hasConnected && reconnectRequired) {
              reconnectRequired = false;
              reconnectRef.current?.();
            }
            hasConnected = true;
          } else if (hasConnected) {
            reconnectRequired = true;
          }
        };

        removeConnectionListener = echo.connector.onConnectionChange(handleConnection);
        handleConnection(echo.connector.connectionStatus());

        echo.private(channelName)
          .subscribed(() => {
            if (active) reconnectRef.current?.();
          })
          .listen(".user.notification.created", (event) => {
            if (active && event.notification_id) notificationRef.current?.(event.notification_id);
          })
          .error(() => {
            if (active) setConnection("failed");
          });
      } catch {
        if (active) setConnection("failed");
      }
    }

    subscribe();

    return () => {
      active = false;
      removeConnectionListener?.();
      echo?.leave(channelName);
    };
  }, [userId]);

  return { connection };
}
