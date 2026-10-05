import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Team chat — Syncora" };

export default async function ProjectChatPage({ params }) {
  const { workspaceSlug, projectSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="chat" projectSlug={projectSlug} />;
}
