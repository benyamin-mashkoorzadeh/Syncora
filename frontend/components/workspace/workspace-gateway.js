"use client";

import BrandMark from "@/components/brand-mark";
import { useAuth } from "@/components/auth/auth-provider";
import ThemeToggle from "@/components/theme-toggle";
import { workspaceApi } from "@/lib/workspace-api";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import styles from "./workspace.module.css";

export default function WorkspaceGateway({ create = false }) {
  const router = useRouter();
  const { status, user } = useAuth();
  const [state, setState] = useState({ loading: true, error: "" });
  const [name, setName] = useState("");
  const [fieldError, setFieldError] = useState("");
  const [pending, setPending] = useState(false);

  useEffect(() => {
    if (status === "unauthenticated") router.replace("/login");
    if (status === "authenticated" && !user.is_verified) router.replace("/verify-email");
    if (status !== "authenticated" || !user.is_verified) return;

    const controller = new AbortController();
    workspaceApi.list({ signal: controller.signal }).then((payload) => {
      if (!create && payload.data.length) {
        router.replace(`/${payload.data[0].slug}`);
        return;
      }
      setState({ loading: false, error: "" });
    }).catch((error) => {
      if (error.name !== "AbortError") setState({ loading: false, error: error.message });
    });
    return () => controller.abort();
  }, [create, router, status, user]);

  async function submit(event) {
    event.preventDefault();
    setPending(true);
    setFieldError("");
    setState((current) => ({ ...current, error: "" }));
    try {
      const payload = await workspaceApi.create({ name });
      router.push(`/${payload.data.slug}`);
    } catch (error) {
      setFieldError(error.errors?.name?.[0] ?? "");
      setState({ loading: false, error: error.errors?.name ? "" : error.message });
      setPending(false);
    }
  }

  if (status === "loading" || state.loading) {
    return <main className={styles.gateway}><p className={styles.muted} role="status">Preparing your workspace…</p></main>;
  }

  return (
    <main className={styles.gateway}>
      <section className={styles.onboarding}>
        <div className={styles.onboardingHeader}>
          <Link className={styles.brand} href="/"><BrandMark className={styles.brandMark} />Syncora</Link>
          <ThemeToggle />
        </div>
        <p className={styles.eyebrow}>{create ? "New workspace" : "Your collaboration space"}</p>
        <h1>{create ? "Create another workspace" : "Give your team a place to move together."}</h1>
        <p>{create ? "Start a separate collaboration boundary with its own members." : "Create your first workspace. You can bring in registered teammates once you’re inside."}</p>
        <form className={styles.form} onSubmit={submit} noValidate>
          <div className={styles.field}>
            <label htmlFor="workspace-name">Workspace name</label>
            <input id="workspace-name" value={name} onChange={(event) => setName(event.target.value)} placeholder="Acme product team" autoFocus required aria-invalid={Boolean(fieldError)} aria-describedby={fieldError ? "workspace-name-error" : undefined} />
            {fieldError ? <span id="workspace-name-error" className={styles.fieldError}>{fieldError}</span> : null}
          </div>
          {state.error ? <p className={styles.noticeError} role="alert">{state.error}</p> : null}
          <button className={styles.primaryButton} type="submit" disabled={pending}>{pending ? "Creating workspace…" : "Create workspace"}</button>
          {create ? <Link className={styles.secondaryButton} href="/workspaces">Cancel</Link> : null}
        </form>
      </section>
    </main>
  );
}
