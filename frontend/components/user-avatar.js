import styles from "./user-avatar.module.css";

function fallbackInitial(name) {
  const normalizedName = typeof name === "string" ? name.trim() : "";

  if (!normalizedName) {
    return "?";
  }

  return Array.from(normalizedName)[0].toLocaleUpperCase();
}

export default function UserAvatar({ name, src = null, className = "" }) {
  const classes = [styles.avatar, className].filter(Boolean).join(" ");

  return (
    <span className={classes} aria-hidden="true">
      {/* Avatar URLs may come from any Laravel filesystem disk, so native images remain storage-provider agnostic. */}
      {/* eslint-disable-next-line @next/next/no-img-element */}
      {src ? <img src={src} alt="" /> : fallbackInitial(name)}
    </span>
  );
}
