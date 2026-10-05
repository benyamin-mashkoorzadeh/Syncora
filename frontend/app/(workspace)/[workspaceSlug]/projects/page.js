import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Projects — Syncora" };

export default async function ProjectsPage({ params }) {
  const { workspaceSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="projects" />;
}
