import ApiStatus from "@/components/api-status";
import BrandMark from "@/components/brand-mark";
import DemoEntryButton from "@/components/demo-entry-button";
import ThemeToggle from "@/components/theme-toggle";
import Link from "next/link";
import styles from "./page.module.css";

const boardColumns = [
  {
    count: 3,
    label: "Planned",
    tone: "planned",
    tasks: [
      {
        title: "Shape the sprint outcome",
        meta: "Planning · Today",
        priority: "High",
        avatars: ["AK", "MS"],
      },
      {
        title: "Map the onboarding flow",
        meta: "Product · Sep 24",
        priority: "Medium",
        avatars: ["NO"],
      },
    ],
  },
  {
    count: 2,
    label: "In progress",
    tone: "active",
    tasks: [
      {
        title: "Refine project navigation",
        meta: "Design system · Today",
        priority: "High",
        avatars: ["SJ", "AK"],
      },
      {
        title: "Review team permissions",
        meta: "Platform · Sep 25",
        priority: "Low",
        avatars: ["MS"],
      },
    ],
  },
  {
    count: 4,
    label: "Done",
    tone: "done",
    tasks: [
      {
        title: "Align product principles",
        meta: "Product · Sep 20",
        priority: "Complete",
        avatars: ["SJ", "NO"],
      },
    ],
  },
];

const principles = [
  {
    number: "01",
    title: "A calm source of truth",
    description:
      "Plans, decisions, and progress stay clear without turning teamwork into dashboard noise.",
  },
  {
    number: "02",
    title: "Momentum you can see",
    description:
      "Purposeful status, presence, and motion make collaboration feel immediate and dependable.",
  },
  {
    number: "03",
    title: "Built around real teams",
    description:
      "Practical Scrum structure meets the flexibility teams need to keep useful work moving.",
  },
];

export default function Home() {
  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <a className={styles.brand} href="#top" aria-label="Syncora home">
          <BrandMark className={styles.brandMark} />
          <span>syncora</span>
        </a>

        <div className={styles.headerActions}>
          <ApiStatus />
          <nav className={styles.authNav} aria-label="Account">
            <Link href="/login">Sign in</Link>
            <Link href="/register">Create account</Link>
          </nav>
          <ThemeToggle />
        </div>
      </header>

      <main id="top">
        <section className={styles.hero} aria-labelledby="hero-title">
          <div className={styles.heroCopy}>
            <div className={styles.eyebrow}>
              <span className={styles.eyebrowDot} />
              Real-time teamwork
            </div>
            <h1 id="hero-title">
              Clear plans.
              <span>Shared momentum.</span>
            </h1>
            <p className={styles.heroDescription}>
              Syncora brings planning, delivery, and team conversation into one
              focused workspace—so everyone can see what matters and move it
              forward together.
            </p>
            <div className={styles.heroActions}>
              <DemoEntryButton
                buttonClassName={styles.primaryAction}
                noteClassName={styles.previewNote}
              />
            </div>
            <ul className={styles.productSignals} aria-label="Product qualities">
              <li>Focused planning</li>
              <li>Live collaboration</li>
              <li>Practical Scrum</li>
            </ul>
          </div>

          <div className={styles.previewWrap} id="preview">
            <div className={styles.ambientOrb} aria-hidden="true" />
            <div className={styles.previewWindow} aria-label="Static Syncora board preview">
              <div className={styles.windowHeader}>
                <div className={styles.windowIdentity}>
                  <BrandMark className={styles.brandMark} />
                  <div>
                    <strong>Northstar</strong>
                    <span>Product workspace</span>
                  </div>
                </div>
                <div className={styles.windowMeta}>
                  <span className={styles.previewBadge}>Product snapshot</span>
                  <div className={styles.avatarGroup} aria-label="Three collaborators">
                    <span>AK</span>
                    <span>MS</span>
                    <span>+2</span>
                  </div>
                </div>
              </div>

              <div className={styles.boardHeading}>
                <div>
                  <span>SPRINT 04</span>
                  <h2>Build a clearer first run</h2>
                </div>
                <span className={styles.sprintBadge}>8 days left</span>
              </div>

              <div className={styles.boardGrid}>
                {boardColumns.map((column) => (
                  <section className={styles.boardColumn} key={column.label}>
                    <div className={styles.columnHeading}>
                      <div>
                        <span
                          className={`${styles.statusDot} ${styles[column.tone]}`}
                        />
                        <h3>{column.label}</h3>
                      </div>
                      <span>{column.count}</span>
                    </div>

                    <div className={styles.taskList}>
                      {column.tasks.map((task) => (
                        <article className={styles.taskCard} key={task.title}>
                          <div className={styles.taskTopline}>
                            <span
                              className={`${styles.priority} ${
                                task.priority === "High"
                                  ? styles.priorityHigh
                                  : task.priority === "Complete"
                                    ? styles.priorityComplete
                                    : styles.priorityDefault
                              }`}
                            >
                              {task.priority}
                            </span>
                            <span className={styles.taskMenu} aria-hidden="true">
                              ···
                            </span>
                          </div>
                          <h4>{task.title}</h4>
                          <div className={styles.taskFooter}>
                            <span>{task.meta}</span>
                            <div className={styles.taskAvatars} aria-hidden="true">
                              {task.avatars.map((avatar) => (
                                <span key={avatar}>{avatar}</span>
                              ))}
                            </div>
                          </div>
                        </article>
                      ))}
                    </div>
                  </section>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section className={styles.principles} aria-labelledby="principles-title">
          <div className={styles.sectionIntro}>
            <span>The Syncora approach</span>
            <h2 id="principles-title">Built for clarity, shaped for collaboration.</h2>
          </div>
          <div className={styles.principleGrid}>
            {principles.map((principle) => (
              <article key={principle.number}>
                <span>{principle.number}</span>
                <h3>{principle.title}</h3>
                <p>{principle.description}</p>
              </article>
            ))}
          </div>
        </section>
      </main>

      <footer className={styles.footer}>
        <div className={styles.brand}>
          <BrandMark className={styles.brandMark} />
          <span>syncora</span>
        </div>
        <p>Real-time planning, delivery, and conversation in one focused workspace.</p>
      </footer>
    </div>
  );
}
