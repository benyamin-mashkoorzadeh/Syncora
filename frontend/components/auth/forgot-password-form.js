"use client";

import {
  Field,
  firstError,
  FormIntro,
  FormNotice,
  SubmitButton,
} from "@/components/auth/auth-form-parts";
import { authApi } from "@/lib/auth-api";
import Link from "next/link";
import { useState } from "react";
import styles from "./auth.module.css";

export default function ForgotPasswordForm() {
  const [email, setEmail] = useState("");
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState("");
  const [sent, setSent] = useState(false);
  const [pending, setPending] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage("");

    try {
      const payload = await authApi.forgotPassword(email);
      setMessage(payload.message);
      setSent(true);
    } catch (error) {
      setErrors(error.errors ?? {});
      setMessage(error.message);
    } finally {
      setPending(false);
    }
  }

  return (
    <>
      <FormIntro
        eyebrow="Account recovery"
        title="Reset your password"
        description="Enter your account email and we’ll send a secure, time-limited reset link if it matches an account."
      />
      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <FormNotice tone={sent ? "success" : "error"}>{message}</FormNotice>
        <Field label="Email address" name="email" error={firstError(errors, "email")}>
          {(fieldProps) => (
            <input
              {...fieldProps}
              type="email"
              autoComplete="email"
              placeholder="you@company.com"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
          )}
        </Field>
        <SubmitButton pending={pending} pendingLabel="Sending link…">
          Send reset link
        </SubmitButton>
      </form>
      <p className={styles.formFooter}>
        Remembered it? <Link href="/login">Return to sign in</Link>
      </p>
    </>
  );
}
