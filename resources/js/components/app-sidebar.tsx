import { Link, usePage } from "@inertiajs/react";
import { Ellipsis, Play, RotateCw, Square, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import AuditLogIcon from "@/components/audit-log-icon";
import BackupsIcon from "@/components/backups-icon";
import CargoIcon from "@/components/cargo-icon";
import ConsoleIcon from "@/components/console-icon";
import DashboardIcon from "@/components/dashboard-icon";
import FilesIcon from "@/components/files-icon";
import LocationsIcon from "@/components/locations-icon";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import NetworkingIcon from "@/components/networking-icon";
import NodesIcon from "@/components/nodes-icon";
import ServerIcon from "@/components/server-icon";
import ServerStatusIndicator from "@/components/server-status-indicator";

import ServerUsersIcon from "@/components/server-users-icon";
import SettingsIcon from "@/components/settings-icon";
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { PlaceholderPattern } from "@/components/ui/placeholder-pattern";
import {
	Sidebar,
	SidebarContent,
	SidebarFooter,
	SidebarHeader,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
} from "@/components/ui/sidebar";
import { toast } from "@/components/ui/sonner";
import UsersIcon from "@/components/users-icon";
import WorkflowsIcon from "@/components/workflows-icon";
import {
	powerActionsForState,
	type ServerRuntimeState,
	statusLabel,
} from "@/lib/server-runtime";
import { cn } from "@/lib/utils";
import { home } from "@/routes";
import { index as adminCargo } from "@/routes/admin/cargo";
import { index as adminLocations } from "@/routes/admin/locations";
import { index as adminNodes } from "@/routes/admin/nodes";
import { index as adminServers } from "@/routes/admin/servers";
import { index as adminSettings } from "@/routes/admin/settings";
import { index as adminUsers } from "@/routes/admin/users";
import {
	power as powerServer,
	console as serverConsole,
	settings as serverSettings,
} from "@/routes/client/servers";
import type { NavItem } from "@/types";

type ServerPowerSignal = "kill" | "restart" | "start" | "stop";

type SidebarServer = {
	id: number;
	name: string;
	status?: string;
};

function csrfToken(): string {
	const token = document
		.querySelector('meta[name="csrf-token"]')
		?.getAttribute("content");

	if (!token) {
		throw new Error("CSRF token not found.");
	}

	return token;
}

function powerActionLabel(signal: ServerPowerSignal): string {
	switch (signal) {
		case "kill":
			return "Kill";
		case "restart":
			return "Restart";
		case "start":
			return "Start";
		case "stop":
			return "Stop";
	}
}

function optimisticStatusForSignal(
	signal: ServerPowerSignal,
	currentStatus: string,
): ServerRuntimeState {
	switch (signal) {
		case "kill":
			return "offline";
		case "restart":
			return "restarting";
		case "start":
			return "starting";
		case "stop":
			return currentStatus === "running" ? "stopping" : "offline";
	}
}

const SERVER_PAGE_PATTERN =
	/^\/server\/\d+\/(files|settings|networking|users|backups)(?:[/?].*)?$/u;

function serverHrefForPage(currentUrl: string, serverId: number): string {
	if (SERVER_PAGE_PATTERN.test(currentUrl)) {
		return currentUrl.replace(/^\/server\/\d+/u, `/server/${serverId}`);
	}

	return serverConsole.url(serverId);
}

function ServerSidebarCard({
	currentUrl,
	server,
	servers,
}: {
	currentUrl: string;
	server: SidebarServer;
	servers: SidebarServer[];
}) {
	const [runtimeState, setRuntimeState] = useState(server.status ?? "offline");
	const [submittingAction, setSubmittingAction] =
		useState<ServerPowerSignal | null>(null);
	const [switcherOpen, setSwitcherOpen] = useState(false);

	useEffect(() => {
		setRuntimeState(server.status ?? "offline");
		setSubmittingAction(null);
	}, [server.id, server.status]);

	const availability = useMemo(
		() => powerActionsForState(runtimeState),
		[runtimeState],
	);

	const switcherServers = useMemo(
		() =>
			servers
				.filter((candidate) => candidate.id !== server.id)
				.sort((left, right) => left.name.localeCompare(right.name)),
		[server.id, servers],
	);

	const sendPowerSignal = async (signal: ServerPowerSignal) => {
		if (submittingAction !== null) {
			return;
		}

		setSubmittingAction(signal);

		try {
			const response = await fetch(powerServer.url(server.id), {
				method: "POST",
				headers: {
					Accept: "application/json",
					"Content-Type": "application/json",
					"X-CSRF-TOKEN": csrfToken(),
					"X-Requested-With": "XMLHttpRequest",
				},
				body: JSON.stringify({ signal }),
			});

			const payload = (await response.json().catch(() => null)) as {
				message?: string;
			} | null;

			if (!response.ok) {
				throw new Error(
					payload?.message || "The power action could not be sent.",
				);
			}

			setRuntimeState(optimisticStatusForSignal(signal, runtimeState));
			toast.success(`${powerActionLabel(signal)} signal sent.`);
		} catch (error) {
			const message =
				error instanceof Error
					? error.message
					: "The power action could not be sent.";

			toast.error(message);
		} finally {
			setSubmittingAction(null);
		}
	};

	return (
		<div className="px-2 pb-3 group-data-[collapsible=icon]:hidden">
			<button
				type="button"
				className="relative w-full cursor-pointer overflow-hidden rounded-xl border border-sidebar-border/70 bg-transparent px-2.5 py-1.5 text-left transition-transform duration-150 ease-out active:scale-95"
				onClick={() => setSwitcherOpen((prev) => !prev)}
				aria-expanded={switcherOpen}
				aria-label="Toggle server switcher"
			>
				<PlaceholderPattern
					patternSize={6}
					className="pointer-events-none absolute inset-0 size-full stroke-sidebar-foreground/35 opacity-[0.16]"
				/>

				<div className="relative flex items-center gap-2">
					<div className="min-w-0 flex-1">
						<div className="flex min-w-0 items-center gap-2">
							<span className="truncate text-sm font-semibold text-sidebar-foreground">
								{server.name}
							</span>
							<ServerStatusIndicator
								status={runtimeState}
								className="h-3.5 w-3.5 shrink-0"
								bare
							/>
						</div>
					</div>

					<DropdownMenu>
						<DropdownMenuTrigger asChild>
							<div
								role="button"
								tabIndex={0}
								className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-sidebar-ring"
								aria-label="Open server power actions"
								onClick={(e) => e.stopPropagation()}
								onKeyDown={(e) => {
									if (e.key === "Enter" || e.key === " ") {
										e.stopPropagation();
									}
								}}
							>
								<Ellipsis className="h-4 w-4" />
							</div>
						</DropdownMenuTrigger>
						<DropdownMenuContent align="end" className="w-44 rounded-xl">
							<DropdownMenuItem
								className="cursor-pointer rounded-lg"
								disabled={!availability.start || submittingAction !== null}
								onSelect={() => void sendPowerSignal("start")}
							>
								<Play className="h-4 w-4" />
								Start
							</DropdownMenuItem>
							<DropdownMenuItem
								className="cursor-pointer rounded-lg"
								disabled={!availability.restart || submittingAction !== null}
								onSelect={() => void sendPowerSignal("restart")}
							>
								<RotateCw className="h-4 w-4" />
								Restart
							</DropdownMenuItem>
							<DropdownMenuItem
								className="cursor-pointer rounded-lg"
								disabled={!availability.stop || submittingAction !== null}
								onSelect={() => void sendPowerSignal("stop")}
							>
								<Square className="h-4 w-4" />
								Stop
							</DropdownMenuItem>
							<DropdownMenuItem
								className="cursor-pointer rounded-lg"
								variant="destructive"
								disabled={!availability.kill || submittingAction !== null}
								onSelect={() => void sendPowerSignal("kill")}
							>
								<X className="h-4 w-4" />
								Kill
							</DropdownMenuItem>
						</DropdownMenuContent>
					</DropdownMenu>
				</div>
			</button>

			<div
				className={cn(
					"overflow-hidden transition-[max-height,opacity] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]",
					switcherOpen ? "max-h-64 opacity-100" : "max-h-0 opacity-0",
				)}
			>
				<div className="space-y-0.5 pt-1.5">
					{switcherServers.length > 0 ? (
						switcherServers.map((candidate) => (
							<Link
								key={candidate.id}
								href={serverHrefForPage(currentUrl, candidate.id)}
								prefetch
								cacheFor="30s"
								className="flex min-w-0 items-center gap-2 rounded-lg px-2.5 py-1.5 text-sidebar-foreground/70 transition-colors duration-150 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
							>
								<ServerStatusIndicator
									status={candidate.status ?? "offline"}
									className="h-3.5 w-3.5 shrink-0"
									bare
								/>
								<span className="truncate text-sm font-medium">
									{candidate.name}
								</span>
							</Link>
						))
					) : (
						<p className="px-2.5 py-1.5 text-sm text-sidebar-foreground/50">
							No other servers yet.
						</p>
					)}
				</div>
			</div>
		</div>
	);
}

type SidebarContext = "home" | "server" | "admin";

function sidebarContextFor(url: string, isAdmin: boolean): SidebarContext {
	if (isAdmin && url.startsWith("/admin")) {
		return "admin";
	}

	if (url.startsWith("/server/")) {
		return "server";
	}

	return "home";
}

const contextDepth: Record<SidebarContext, number> = {
	home: 0,
	admin: 1,
	server: 1,
};

export function AppSidebar() {
	const page = usePage();
	const { auth, name, server, serverSwitcher } =
		page.props as typeof page.props & {
			server?: SidebarServer;
			serverSwitcher?: SidebarServer[];
		};
	const isAdminSidebar = auth.user.is_admin && page.url.startsWith("/admin");
	const isServerSidebar = page.url.startsWith("/server/");
	const adminDashboardHref = "/admin";
	const adminActivityHref = "/admin/activity";

	const currentContext = sidebarContextFor(page.url, auth.user.is_admin);
	const prevContextRef = useRef<SidebarContext>(currentContext);
	const slideKeyRef = useRef(0);
	const slideClassRef = useRef("");

	if (prevContextRef.current !== currentContext) {
		const prev = prevContextRef.current;
		const prevDepth = contextDepth[prev];
		const nextDepth = contextDepth[currentContext];

		let direction: "left" | "right" = "right";

		if (nextDepth < prevDepth) {
			direction = "left";
		} else if (nextDepth === prevDepth && prev === "server") {
			direction = "left";
		}

		prevContextRef.current = currentContext;
		slideKeyRef.current += 1;
		slideClassRef.current =
			direction === "right"
				? "animate-slide-in-right"
				: "animate-slide-in-left";
	}
	const availableServers = serverSwitcher ?? [];
	const serverId = server?.id;
	const mainNavItems: NavItem[] = useMemo(() => {
		if (isAdminSidebar) {
			return [
				{
					title: "Overview",
					href: adminDashboardHref,
					icon: DashboardIcon,
				},
				{
					title: "Users",
					href: adminUsers.url(),
					icon: UsersIcon,
				},
				{
					title: "Cargo",
					href: adminCargo.url(),
					icon: CargoIcon,
				},
				{
					title: "Locations",
					href: adminLocations.url(),
					icon: LocationsIcon,
				},
				{
					title: "Nodes",
					href: adminNodes.url(),
					icon: NodesIcon,
				},
				{
					title: "Servers",
					href: adminServers.url(),
					icon: ServerIcon,
				},
				{
					title: "Activity",
					href: adminActivityHref,
					icon: AuditLogIcon,
				},
				{
					title: "Settings",
					href: adminSettings.url(),
					icon: SettingsIcon,
				},
			];
		}

		if (isServerSidebar && serverId) {
			return [
				{
					title: "Console",
					href: serverConsole.url(serverId),
					icon: ConsoleIcon,
				},
				{
					title: "Files",
					href: `/server/${serverId}/files`,
					icon: FilesIcon,
				},
				{
					title: "Networking",
					icon: NetworkingIcon,
					pinnable: false,
					items: [
						{
							title: "Allocations",
							href: `/server/${serverId}/networking/allocations`,
						},
						{
							title: "Firewall",
							href: `/server/${serverId}/networking/firewall`,
						},
						{
							title: "Interconnect",
							href: `/server/${serverId}/networking/interconnect`,
						},
					],
				},
				{
					title: "Backups",
					href: `/server/${serverId}/backups`,
					icon: BackupsIcon,
				},
				{
					title: "Workflows",
					href: `/server/${serverId}/workflows`,
					icon: WorkflowsIcon,
				},
				{
					title: "Users",
					href: `/server/${serverId}/users`,
					icon: ServerUsersIcon,
				},
				{
					title: "Settings",
					href: serverSettings.url(serverId),
					icon: SettingsIcon,
				},
			];
		}

		return [
			{
				title: "Home",
				href: home(),
				icon: DashboardIcon,
			},
		];
	}, [isAdminSidebar, isServerSidebar, serverId]);

	return (
		<Sidebar collapsible="icon" variant="inset">
			<SidebarHeader>
				<SidebarMenu>
					<SidebarMenuItem>
						<SidebarMenuButton size="lg" asChild>
							<Link href={home()} prefetch cacheFor="1m">
								<div className="relative flex h-8 w-full items-center overflow-hidden group-data-[collapsible=icon]:justify-center">
									<span className="text-lg tracking-tight font-semibold group-data-[collapsible=icon]:hidden">
										{name}
									</span>
									<img
										src="https://i.ibb.co/qL4qgHB4/ETHER-2026-04-04-T141225-676.png"
										className="absolute h-7 w-7 rounded object-contain opacity-0 invert transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] group-data-[collapsible=icon]:scale-100 group-data-[collapsible=icon]:opacity-100 group-data-[state=expanded]:scale-90 dark:invert-0"
									/>
								</div>
							</Link>
						</SidebarMenuButton>
					</SidebarMenuItem>
				</SidebarMenu>
			</SidebarHeader>

			<SidebarContent className="!overflow-x-clip">
				<div
					key={slideKeyRef.current}
					className={cn("flex flex-col", slideClassRef.current)}
				>
					{isServerSidebar && server ? (
						<ServerSidebarCard
							currentUrl={page.url}
							server={server}
							servers={availableServers}
						/>
					) : null}

					<NavMain
						items={mainNavItems}
						label={
							isAdminSidebar ? "Admin" : isServerSidebar ? "Server" : "Platform"
						}
					/>
				</div>
			</SidebarContent>

			<SidebarFooter>
				{isServerSidebar && server && auth.user.is_admin && (
					<div className="px-2 group-data-[collapsible=icon]:hidden">
						<Link
							href={adminServers.url({ query: { search: server.name } })}
							className="relative flex items-center justify-between overflow-hidden rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 text-xs font-medium text-sidebar-foreground/70 transition-all duration-150 hover:bg-sidebar-accent hover:text-sidebar-foreground active:scale-95 active:border-brand/30 active:bg-brand/8 active:text-brand"
						>
							<PlaceholderPattern
								patternSize={5}
								className="pointer-events-none absolute inset-0 size-full stroke-sidebar-foreground/35 opacity-[0.08]"
							/>
							<span className="relative">Open in admin panel</span>
							<svg
								className="relative h-3.5 w-3.5"
								viewBox="0 0 24 24"
								strokeWidth="1.5"
								fill="none"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path
									d="M6 19L19 6M19 6V18.48M19 6H6.52"
									stroke="currentColor"
									strokeWidth="1.5"
									strokeLinecap="round"
									strokeLinejoin="round"
								/>
							</svg>
						</Link>
					</div>
				)}
				<NavUser />
			</SidebarFooter>
		</Sidebar>
	);
}
