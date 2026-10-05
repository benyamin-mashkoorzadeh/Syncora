import ResetPasswordForm from "@/components/auth/reset-password-form";

export const metadata = { title: "Reset password — Syncora" };

export default async function ResetPasswordPage({ searchParams }) {
  const params = await searchParams;
  const email = typeof params.email === "string" ? params.email : "";
  const token = typeof params.token === "string" ? params.token : "";

  return <ResetPasswordForm initialEmail={email} token={token} />;
}
