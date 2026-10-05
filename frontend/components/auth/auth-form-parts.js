import styles from "./auth.module.css";

export function FormIntro({ eyebrow, title, description }) {
  return (
    <div className={styles.formIntro}>
      <span>{eyebrow}</span>
      <h1>{title}</h1>
      <p>{description}</p>
    </div>
  );
}

export function Field({
  label,
  name,
  error,
  hint,
  children,
}) {
  const errorId = `${name}-error`;
  const hintId = `${name}-hint`;

  return (
    <div className={styles.field}>
      <label htmlFor={name}>{label}</label>
      {children({
        id: name,
        name,
        "aria-invalid": Boolean(error),
        "aria-describedby": error ? errorId : hint ? hintId : undefined,
      })}
      {error ? (
        <p className={styles.fieldError} id={errorId}>
          {error}
        </p>
      ) : hint ? (
        <p className={styles.fieldHint} id={hintId}>
          {hint}
        </p>
      ) : null}
    </div>
  );
}

export function FormNotice({ tone = "error", children }) {
  if (!children) {
    return null;
  }

  return (
    <div
      className={`${styles.notice} ${styles[tone]}`}
      role={tone === "error" ? "alert" : "status"}
      aria-live="polite"
    >
      {children}
    </div>
  );
}

export function SubmitButton({ pending, pendingLabel, children }) {
  return (
    <button className={styles.submitButton} type="submit" disabled={pending}>
      {pending ? <span className={styles.spinner} aria-hidden="true" /> : null}
      {pending ? pendingLabel : children}
    </button>
  );
}

export function firstError(errors, field) {
  return errors?.[field]?.[0] ?? null;
}
