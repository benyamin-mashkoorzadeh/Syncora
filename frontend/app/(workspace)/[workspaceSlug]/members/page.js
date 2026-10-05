import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Workspace members — Syncora" };

export default async function MembersPage({ params }) {
  const { workspaceSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="members" />;
}
