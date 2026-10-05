"use client";

import { useAuth } from "@/components/auth/auth-provider";
import {
  Field,
  firstError,
  FormIntro,
  FormNotice,
  SubmitButton,
} from "@/components/auth/auth-form-parts";
import { authApi } from "@/lib/auth-api";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import styles from "./auth.module.css";

export default function LoginForm({ nextPath = null }) {
  const router = useRouter();
  const { setAuthenticatedUser } = useAuth();
  const [values, setValues] = useState({ email: "", password: "" });
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);

  function updateValue(event) {
    setValues((current) => ({ ...current, [event.target.name]: event.target.value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setPending(true);
    setErrors({});
    setMessage("");

    try {
      const payload = await authApi.login(values);
      setAuthenticatedUser(payload.data);
      router.push(
        nextPath ?? (payload.data.is_verified ? "/workspaces" : "/verify-email"),
      );
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
        eyebrow="Welcome back"
        title="Sign in to Syncora"
        description="Continue where your team left off with a secure session-backed sign in."
      />
      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <FormNotice>{message}</FormNotice>
        <Field label="Email address" name="email" error={firstError(errors, "email")}>
          {(fieldProps) => (
            <input
              {...fieldProps}
              type="email"
              autoComplete="email"
              placeholder="you@company.com"
              value={values.email}
              onChange={updateValue}
              required
            />
          )}
        </Field>
        <Field label="Password" name="password" error={firstError(errors, "password")}>
          {(fieldProps) => (
            <input
              {...fieldProps}
              type="password"
              autoComplete="current-password"
              placeholder="Your password"
              value={values.password}
              onChange={updateValue}
              required
            />
          )}
        </Field>
        <div className={styles.fieldRow}>
          <Link className={styles.inlineLink} href="/forgot-password">
            Forgot password?
          </Link>
        </div>
        <SubmitButton pending={pending} pendingLabel="Signing in…">
          Sign in
        </SubmitButton>
      </form>
      <p className={styles.formFooter}>
        New to Syncora? <Link href="/register">Create an account</Link>
      </p>
    </>
  );
}
