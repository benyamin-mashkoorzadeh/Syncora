import LoginForm from "@/components/auth/login-form";

export const metadata = { title: "Sign in — Syncora" };

export default async function LoginPage({ searchParams }) {
  const params = await searchParams;
  const requestedPath = typeof params.next === "string" ? params.next : null;
  const nextPath =
    requestedPath?.startsWith("/") && !requestedPath.startsWith("//")
      ? requestedPath
      : null;

  return <LoginForm nextPath={nextPath} />;
}
