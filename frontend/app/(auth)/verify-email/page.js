import VerifyEmailPanel from "@/components/auth/verify-email-panel";

export const metadata = { title: "Verify email — Syncora" };

export default async function VerifyEmailPage({ searchParams }) {
  const params = await searchParams;
  const keys = ["id", "hash", "expires", "signature"];
  const hasCompleteLink = keys.every((key) => typeof params[key] === "string");
  const verification = hasCompleteLink
    ? Object.fromEntries(keys.map((key) => [key, params[key]]))
    : null;

  return <VerifyEmailPanel verification={verification} />;
}
