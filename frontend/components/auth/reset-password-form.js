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

export default function ResetPasswordForm({ initialEmail, token }) {
  const [values, setValues] = useState({
    email: initialEmail,
    password: "",
    password_confirmation: "",
  });
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState("");
  const [complete, setComplete] = useState(false);
  const [pending, setPending] = useState(false);
  const hasResetLink = Boolean(token && initialEmail);

  function updateValue(event) {
    setValues((current) => ({ ...current, [event.target.name]: event.target.value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage("");

    try {
      const payload = await authApi.resetPassword({ ...values, token });
      setMessage(payload.message);
      setComplete(true);
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
        eyebrow="Choose a new password"
        title="Restore secure access"
        description="Your new password will replace the previous credential for this Syncora account."
      />
      {!hasResetLink ? (
        <div className={styles.statusPanel}>
          <FormNotice>This reset link is incomplete or invalid.</FormNotice>
          <Link className={styles.secondaryButton} href="/forgot-password">
            Request a new link
          </Link>
        </div>
      ) : complete ? (
        <div className={styles.statusPanel}>
          <div className={styles.statusIcon} aria-hidden="true">✓</div>
          <h2>Password updated</h2>
          <FormNotice tone="success">{message}</FormNotice>
          <Link className={styles.submitButton} href="/login">
            Continue to sign in
          </Link>
        </div>
      ) : (
        <form className={styles.form} onSubmit={handleSubmit} noValidate>
          <FormNotice>{message}</FormNotice>
          <Field label="Email address" name="email" error={firstError(errors, "email")}>
            {(fieldProps) => (
              <input
                {...fieldProps}
                type="email"
                autoComplete="email"
                value={values.email}
                onChange={updateValue}
                required
              />
            )}
          </Field>
          <Field
            label="New password"
            name="password"
            error={firstError(errors, "password")}
            hint="Use 8+ characters with upper and lowercase letters and a number."
          >
            {(fieldProps) => (
              <input
                {...fieldProps}
                type="password"
                autoComplete="new-password"
                value={values.password}
                onChange={updateValue}
                required
              />
            )}
          </Field>
          <Field
            label="Confirm new password"
            name="password_confirmation"
            error={firstError(errors, "password_confirmation")}
          >
            {(fieldProps) => (
              <input
                {...fieldProps}
                type="password"
                autoComplete="new-password"
                value={values.password_confirmation}
                onChange={updateValue}
                required
              />
            )}
          </Field>
          <SubmitButton pending={pending} pendingLabel="Updating password…">
            Update password
          </SubmitButton>
        </form>
      )}
    </>
  );
}
