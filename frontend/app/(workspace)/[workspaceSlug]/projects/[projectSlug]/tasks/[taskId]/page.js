import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Task — Syncora" };

export default async function TaskPage({ params }) {
  const { workspaceSlug, projectSlug, taskId } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="task" projectSlug={projectSlug} taskId={taskId} />;
}
