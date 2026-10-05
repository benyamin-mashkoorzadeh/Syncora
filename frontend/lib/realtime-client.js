import {
  backendUrl,
  initializeCsrf,
  readCookie,
  registerRealtimeSocketIdProvider,
} from "@/lib/api-client";

let echoPromise = null;

async function authorizeChannel(params, callback) {
  try {
    await initializeCsrf();
    const headers = {
      Accept: "application/json",
      "Content-Type": "application/x-www-form-urlencoded",
    };
    const token = readCookie("XSRF-TOKEN");

    if (token) {
      headers["X-XSRF-TOKEN"] = token;
    }

    const response = await fetch(`${backendUrl}/broadcasting/auth`, {
      method: "POST",
      credentials: "include",
      headers,
      body: new URLSearchParams({
        socket_id: params.socketId,
        channel_name: params.channelName,
      }),
    });

    if (!response.ok) {
      callback(new Error(`Channel authorization failed with status ${response.status}.`), null);
      return;
    }

    callback(null, await response.json());
  } catch (error) {
    callback(error, null);
  }
}

export async function getRealtimeClient() {
  if (typeof window === "undefined") {
    return null;
  }

  if (!process.env.NEXT_PUBLIC_REVERB_APP_KEY) {
    throw new Error("Real-time configuration is unavailable.");
  }

  if (!echoPromise) {
    echoPromise = Promise.all([
      import("laravel-echo"),
      import("pusher-js"),
    ]).then(([echoModule, pusherModule]) => {
      const Echo = echoModule.default;
      const Pusher = pusherModule.default;
      const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https";
      const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? (scheme === "https" ? 443 : 80));
      const echo = new Echo({
        broadcaster: "reverb",
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === "https",
        enabledTransports: ["ws", "wss"],
        Pusher,
        channelAuthorization: {
          customHandler: authorizeChannel,
        },
      });

      registerRealtimeSocketIdProvider(() => echo.socketId());

      return echo;
    }).catch((error) => {
      echoPromise = null;
      throw error;
    });
  }

  return echoPromise;
}
