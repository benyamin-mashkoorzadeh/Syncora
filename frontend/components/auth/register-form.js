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

export default function RegisterForm() {
  const router = useRouter();
  const { setAuthenticatedUser } = useAuth();
  const [values, setValues] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
  });
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
      const payload = await authApi.register(values);
      setAuthenticatedUser(payload.data);
      router.push("/verify-email");
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
        eyebrow="Start with clarity"
        title="Create your account"
        description="Set up your secure Syncora identity, verify your email, and create your first workspace."
      />
      <form className={styles.form} onSubmit={handleSubmit} noValidate>
        <FormNotice>{message}</FormNotice>
        <Field label="Full name" name="name" error={firstError(errors, "name")}>
          {(fieldProps) => (
            <input
              {...fieldProps}
              type="text"
              autoComplete="name"
              placeholder="Alex Morgan"
              value={values.name}
              onChange={updateValue}
              required
            />
          )}
        </Field>
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
        <Field
          label="Password"
          name="password"
          error={firstError(errors, "password")}
          hint="Use 8+ characters with upper and lowercase letters and a number."
        >
          {(fieldProps) => (
            <input
              {...fieldProps}
              type="password"
              autoComplete="new-password"
              placeholder="Create a password"
              value={values.password}
              onChange={updateValue}
              required
            />
          )}
        </Field>
        <Field
          label="Confirm password"
          name="password_confirmation"
          error={firstError(errors, "password_confirmation")}
        >
          {(fieldProps) => (
            <input
              {...fieldProps}
              type="password"
              autoComplete="new-password"
              placeholder="Repeat your password"
              value={values.password_confirmation}
              onChange={updateValue}
              required
            />
          )}
        </Field>
        <SubmitButton pending={pending} pendingLabel="Creating account…">
          Create account
        </SubmitButton>
      </form>
      <p className={styles.formFooter}>
        Already have an account? <Link href="/login">Sign in</Link>
      </p>
    </>
  );
}
