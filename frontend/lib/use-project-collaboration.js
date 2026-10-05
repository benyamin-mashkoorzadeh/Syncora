"use client";

import { getRealtimeClient } from "@/lib/realtime-client";
import { useEffect, useRef, useState } from "react";

function uniqueMembers(members) {
  return Array.from(new Map(members.map((member) => [String(member.id), member])).values());
}

export function useProjectCollaboration({ projectId, onBoardChanged, onMessageCreated, onReconnect }) {
  const boardChangedRef = useRef(onBoardChanged);
  const messageCreatedRef = useRef(onMessageCreated);
  const reconnectRef = useRef(onReconnect);
  const [connection, setConnection] = useState("connecting");
  const [members, setMembers] = useState([]);

  useEffect(() => {
    boardChangedRef.current = onBoardChanged;
    messageCreatedRef.current = onMessageCreated;
    reconnectRef.current = onReconnect;
  }, [onBoardChanged, onMessageCreated, onReconnect]);

  useEffect(() => {
    if (!projectId) return;

    let active = true;
    let echo = null;
    let removeConnectionListener = null;
    let hasConnected = false;
    let reconnectRequired = false;
    const channelName = `project.${projectId}.collaboration`;

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

        echo.join(channelName)
          .here((currentMembers) => {
            if (active) setMembers(uniqueMembers(currentMembers));
          })
          .joining((member) => {
            if (active) setMembers((current) => uniqueMembers([...current, member]));
          })
          .leaving((member) => {
            if (active) setMembers((current) => current.filter((item) => String(item.id) !== String(member.id)));
          })
          .listen(".project.board.changed", (event) => {
            if (active && Number(event.project_id) === Number(projectId)) {
              boardChangedRef.current?.(event);
            }
          })
          .listen(".project.chat.message.created", (event) => {
            if (active && Number(event.project_id) === Number(projectId)) {
              messageCreatedRef.current?.(event.message);
            }
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
      setMembers([]);
      removeConnectionListener?.();
      echo?.leave(channelName);
    };
  }, [projectId]);

  return { connection, members };
}
