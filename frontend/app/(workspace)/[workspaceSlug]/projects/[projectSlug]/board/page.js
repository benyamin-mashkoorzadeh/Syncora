import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Project board — Syncora" };

export default async function ProjectBoardPage({ params }) {
  const { workspaceSlug, projectSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="board" projectSlug={projectSlug} />;
}
