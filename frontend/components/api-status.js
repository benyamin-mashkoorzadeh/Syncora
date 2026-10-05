"use client";

import { useEffect, useState } from "react";
import styles from "./foundation-controls.module.css";

const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

const labels = {
  checking: "Checking API",
  ready: "API ready",
  unavailable: "API unavailable",
};

export default function ApiStatus() {
  const [status, setStatus] = useState("checking");

  useEffect(() => {
    const controller = new AbortController();

    async function checkApi() {
      try {
        const response = await fetch(`${apiUrl}/health`, {
          credentials: "include",
          headers: { Accept: "application/json" },
          signal: controller.signal,
        });
        const payload = await response.json();

        setStatus(
          response.ok && payload?.data?.status === "ok" ? "ready" : "unavailable",
        );
      } catch (error) {
        if (error.name !== "AbortError") {
          setStatus("unavailable");
        }
      }
    }

    checkApi();

    return () => controller.abort();
  }, []);

  return (
    <span
      className={`${styles.apiStatus} ${styles[status]}`}
      role="status"
      aria-live="polite"
    >
      <span aria-hidden="true" />
      {labels[status]}
    </span>
  );
}
