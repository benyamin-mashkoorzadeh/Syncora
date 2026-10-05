import BrandMark from "@/components/brand-mark";
import ThemeToggle from "@/components/theme-toggle";
import Link from "next/link";
import styles from "@/components/auth/auth.module.css";

export default function AuthLayout({ children }) {
  return (
    <div className={styles.authPage}>
      <header className={styles.authHeader}>
        <Link className={styles.brand} href="/" aria-label="Syncora home">
          <BrandMark className={styles.brandMark} />
          <span>syncora</span>
        </Link>
        <ThemeToggle />
      </header>

      <main className={styles.authMain}>
        <section className={styles.authStory} aria-labelledby="auth-story-title">
          <div>
            <span className={styles.storyEyebrow}>A shared place to move work forward</span>
            <h2 id="auth-story-title">Return to clarity. Continue with momentum.</h2>
            <p>
              Your secure Syncora session keeps planning and collaboration close,
              while Laravel remains the authority behind every account action.
            </p>
          </div>
          <ul className={styles.storySignals} aria-label="Authentication qualities">
            <li>Secure session access</li>
            <li>Focused by design</li>
            <li>Ready for real teams</li>
          </ul>
        </section>

        <section className={styles.authCard}>{children}</section>
      </main>

      <footer className={styles.authFooter}>
        <span>Syncora</span>
        <span>One focused module at a time.</span>
      </footer>
    </div>
  );
}
