import WorkspaceArea from "@/components/workspace/workspace-area";

export default async function WorkspacePage({ params }) {
  const { workspaceSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} />;
}
