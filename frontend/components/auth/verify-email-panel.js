"use client";

import { useAuth } from "@/components/auth/auth-provider";
import { FormIntro, FormNotice } from "@/components/auth/auth-form-parts";
import { authApi } from "@/lib/auth-api";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import styles from "./auth.module.css";

export default function VerifyEmailPanel({ verification }) {
  const { status, user, setAuthenticatedUser } = useAuth();
  const attempted = useRef(false);
  const [pending, setPending] = useState(Boolean(verification));
  const [resending, setResending] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    if (!verification || status !== "authenticated" || attempted.current) {
      return;
    }

    attempted.current = true;

    async function verify() {
      try {
        const payload = await authApi.verifyEmail(verification);
        setAuthenticatedUser(payload.data);
        setMessage(payload.message);
      } catch (requestError) {
        setError(
          requestError.status === 403
            ? "This verification link is invalid or has expired. Request a fresh link below."
            : requestError.message,
        );
      } finally {
        setPending(false);
      }
    }

    verify();
  }, [setAuthenticatedUser, status, verification]);

  async function resend() {
    setResending(true);
    setError("");
    setMessage("");

    try {
      const payload = await authApi.resendVerification();
      setMessage(payload.message);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setResending(false);
    }
  }

  const isVerified = user?.is_verified;
  const returnPath = verification
    ? `/verify-email?${new URLSearchParams(verification)}`
    : "/verify-email";

  return (
    <>
      <FormIntro
        eyebrow="Email verification"
        title={isVerified ? "Email confirmed" : "Check your inbox"}
        description={
          isVerified
            ? "Your email is verified and your Syncora identity is ready."
            : "Confirm your email address so your account is ready for the collaboration modules ahead."
        }
      />
      <div className={styles.statusPanel}>
        <div className={styles.statusIcon} aria-hidden="true">
          {pending || status === "loading" ? "…" : isVerified ? "✓" : "@"}
        </div>

        {status === "loading" || pending ? (
          <p role="status">Confirming your account state…</p>
        ) : status === "unauthenticated" ? (
          <>
            <FormNotice>
              Sign in with the account that received this verification link, then open it again.
            </FormNotice>
            <Link
              className={styles.submitButton}
              href={`/login?next=${encodeURIComponent(returnPath)}`}
            >
              Continue to sign in
            </Link>
          </>
        ) : status === "error" ? (
          <FormNotice>Syncora could not load your account. Please refresh and try again.</FormNotice>
        ) : isVerified ? (
          <>
            <FormNotice tone="success">{message || "Your email address is verified."}</FormNotice>
            <Link className={styles.submitButton} href="/workspaces">
              Continue to workspaces
            </Link>
          </>
        ) : (
          <>
            <h2>Verification required</h2>
            <p>
              We sent a signed verification link to <strong>{user?.email}</strong>.
              The link expires after 60 minutes.
            </p>
            <FormNotice>{error}</FormNotice>
            <FormNotice tone="success">{message}</FormNotice>
            <button
              className={styles.secondaryButton}
              type="button"
              onClick={resend}
              disabled={resending}
            >
              {resending ? "Sending…" : "Send a fresh verification link"}
            </button>
            <Link className={styles.textAction} href="/account">
              Go to account
            </Link>
          </>
        )}
      </div>
    </>
  );
}
