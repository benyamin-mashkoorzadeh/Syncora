"use client";

import { useAuth } from "@/components/auth/auth-provider";
import { authApi } from "@/lib/auth-api";
import { useRouter } from "next/navigation";
import { useState } from "react";

function ArrowIcon() {
  return (
    <svg viewBox="0 0 20 20" aria-hidden="true">
      <path d="M4 10h11M11 6l4 4-4 4" />
    </svg>
  );
}

export default function DemoEntryButton({ buttonClassName, noteClassName }) {
  const router = useRouter();
  const { setAuthenticatedUser } = useAuth();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");

  async function enterDemo() {
    if (pending) return;
    setPending(true);
    setError("");

    try {
      const payload = await authApi.demoLogin();
      setAuthenticatedUser(payload.data);
      router.push(`/${payload.demo.workspace_slug}`);
    } catch (requestError) {
      setError(requestError.status === 409
        ? "Sign out of your current account before entering the shared demo."
        : requestError.message);
      setPending(false);
    }
  }

  return (
    <>
      <button className={buttonClassName} type="button" onClick={enterDemo} disabled={pending}>
        {pending ? "Entering demo…" : "Explore Demo"}
        <ArrowIcon />
      </button>
      <span className={noteClassName} role={error ? "alert" : undefined}>
        {error || "Open a populated workspace—no signup required."}
      </span>
    </>
  );
}
