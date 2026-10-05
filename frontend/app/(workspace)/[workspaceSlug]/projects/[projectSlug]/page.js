import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Project — Syncora" };

export default async function ProjectPage({ params }) {
  const { workspaceSlug, projectSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="project" projectSlug={projectSlug} />;
}
