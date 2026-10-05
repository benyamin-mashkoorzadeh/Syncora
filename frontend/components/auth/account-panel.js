"use client";

import { useAuth } from "@/components/auth/auth-provider";
import { FormIntro, FormNotice } from "@/components/auth/auth-form-parts";
import { authApi } from "@/lib/auth-api";
import UserAvatar from "@/components/user-avatar";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import styles from "./auth.module.css";

export default function AccountPanel() {
  const router = useRouter();
  const { status, user, error: authError, clearUser, refreshUser, setAuthenticatedUser } = useAuth();
  const fileInputRef = useRef(null);
  const [pending, setPending] = useState(false);
  const [avatarPending, setAvatarPending] = useState(false);
  const [avatarError, setAvatarError] = useState("");
  const [avatarMessage, setAvatarMessage] = useState("");
  const [message, setMessage] = useState("");

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [router, status]);

  async function logout() {
    setPending(true);
    setMessage("");

    try {
      await authApi.logout();
      clearUser();
      router.replace("/login");
    } catch (requestError) {
      setMessage(requestError.message);
      setPending(false);
    }
  }

  async function uploadAvatar(event) {
    event.preventDefault();
    const avatar = fileInputRef.current?.files?.[0];
    if (!avatar || avatarPending) return;
    setAvatarPending(true);
    setAvatarError("");
    setAvatarMessage("");

    try {
      const payload = await authApi.updateAvatar(avatar);
      setAuthenticatedUser(payload.data);
      fileInputRef.current.value = "";
      setAvatarMessage(payload.message);
    } catch (requestError) {
      setAvatarError(requestError.errors?.avatar?.[0] ?? requestError.message);
    } finally {
      setAvatarPending(false);
    }
  }

  async function removeAvatar() {
    if (!user.avatar_url || avatarPending) return;
    setAvatarPending(true);
    setAvatarError("");
    setAvatarMessage("");

    try {
      const payload = await authApi.removeAvatar();
      setAuthenticatedUser(payload.data);
      setAvatarMessage(payload.message);
    } catch (requestError) {
      setAvatarError(requestError.message);
    } finally {
      setAvatarPending(false);
    }
  }

  if (status === "loading" || status === "unauthenticated") {
    return (
      <>
        <FormIntro
          eyebrow="Secure account"
          title="Loading your account"
          description="Syncora is confirming your authoritative Laravel session."
        />
        <div className={styles.statusPanel}>
          <div className={styles.statusIcon} aria-hidden="true">…</div>
          <p role="status">Checking your session…</p>
        </div>
      </>
    );
  }

  if (status === "error") {
    return (
      <>
        <FormIntro
          eyebrow="Connection issue"
          title="We couldn’t load your account"
          description="Your browser session is unchanged. Retry when the Laravel API is reachable."
        />
        <div className={styles.statusPanel}>
          <FormNotice>{authError}</FormNotice>
          <button className={styles.secondaryButton} type="button" onClick={() => refreshUser()}>
            Retry account check
          </button>
        </div>
      </>
    );
  }

  return (
    <>
      <FormIntro
        eyebrow="Authenticated"
        title={`Welcome, ${user.name}`}
        description={user.is_verified
          ? "Your verified account is ready for the Syncora workspace experience."
          : "Your account is secure. Verify your email before entering a workspace."}
      />
      <div className={styles.statusPanel}>
        <div className={styles.accountIdentity}>
          <UserAvatar className={styles.accountAvatar} name={user.name} src={user.avatar_url} />
          <div>
          <h2>{user.name}</h2>
          <p>{user.email}</p>
          </div>
        </div>
        <FormNotice tone={user.is_verified ? "success" : "error"}>
          {user.is_verified
            ? "Email verified"
            : "Email verification is still pending."}
        </FormNotice>
        <form className={styles.avatarForm} onSubmit={uploadAvatar}>
          <div>
            <label htmlFor="profile-avatar">Profile image</label>
            <p>JPEG, PNG, or WebP. Maximum 2 MB.</p>
          </div>
          <input ref={fileInputRef} id="profile-avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required disabled={avatarPending} />
          <div className={styles.avatarActions}>
            <button className={styles.secondaryButton} type="submit" disabled={avatarPending}>{avatarPending ? "Saving…" : user.avatar_url ? "Replace image" : "Upload image"}</button>
            {user.avatar_url ? <button className={styles.removeAvatarButton} type="button" onClick={removeAvatar} disabled={avatarPending}>Remove image</button> : null}
          </div>
          {avatarError ? <FormNotice>{avatarError}</FormNotice> : null}
          {avatarMessage ? <FormNotice tone="success">{avatarMessage}</FormNotice> : null}
        </form>
        <div className={styles.buttonStack}>
          {user.is_verified ? (
            <Link className={styles.secondaryButton} href="/workspaces">
              Go to workspaces
            </Link>
          ) : (
            <Link className={styles.secondaryButton} href="/verify-email">
              Verify email
            </Link>
          )}
          <button
            className={styles.submitButton}
            type="button"
            onClick={logout}
            disabled={pending}
          >
            {pending ? "Signing out…" : "Sign out"}
          </button>
        </div>
        <FormNotice>{message}</FormNotice>
      </div>
    </>
  );
}
