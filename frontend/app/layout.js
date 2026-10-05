import "./globals.css";
import { AuthProvider } from "@/components/auth/auth-provider";

const themeScript = `
  (function () {
    try {
      var savedTheme = localStorage.getItem("syncora-theme");
      var theme = savedTheme === "light" || savedTheme === "dark"
        ? savedTheme
        : window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light";
      document.documentElement.dataset.theme = theme;
      document.documentElement.style.colorScheme = theme;
    } catch (error) {}
  })();
`;

export const metadata = {
  title: "Syncora — Shared momentum for modern teams",
  description:
    "A focused collaboration workspace for planning, delivery, and team momentum.",
};

export default function RootLayout({ children }) {
  return (
    <html
      lang="en"
      data-theme="light"
      data-scroll-behavior="smooth"
      suppressHydrationWarning
    >
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeScript }} />
      </head>
      <body>
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
