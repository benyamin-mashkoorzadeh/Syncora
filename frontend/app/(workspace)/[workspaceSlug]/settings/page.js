import WorkspaceArea from "@/components/workspace/workspace-area";

export const metadata = { title: "Workspace settings — Syncora" };

export default async function SettingsPage({ params }) {
  const { workspaceSlug } = await params;
  return <WorkspaceArea slug={workspaceSlug} view="settings" />;
}
