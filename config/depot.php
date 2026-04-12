<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skyport Depot Source
    |--------------------------------------------------------------------------
    |
    | Reference URL for the upstream depot repository this catalog mirrors.
    | The Docker image tags below come from the same namespace.
    |
    */

    'source_url' => 'https://github.com/skyportsh/depot',

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    'categories' => [
        [
            'key' => 'minecraft',
            'label' => 'Minecraft',
            'description' => 'Vanilla and modded Java Minecraft servers.',
        ],
        [
            'key' => 'game',
            'label' => 'Games',
            'description' => 'Dedicated game servers running on the depot images.',
        ],
        [
            'key' => 'runtime',
            'label' => 'Runtimes',
            'description' => 'Generic language runtimes for custom workloads.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog Entries
    |--------------------------------------------------------------------------
    |
    | Each entry compiles into a Skyport Cargo with the matching slug. The
    | install scripts run inside the listed installer container, which is
    | itself a depot image (ghcr.io/skyportsh/installers:debian).
    |
    */

    'items' => [

        // ─── Minecraft ─────────────────────────────────────────────────────

        [
            'key' => 'minecraft-vanilla',
            'category' => 'minecraft',
            'icon' => 'cube',
            'name' => 'Minecraft: Vanilla',
            'author' => 'skyport',
            'description' => 'Official Mojang Minecraft Java server. Latest release by default; pick any version.',
            'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -Dterminal.jline=false -Dterminal.ansi=true -jar {{SERVER_JARFILE}}',
            'docker_images' => [
                'Java 21' => 'ghcr.io/skyportsh/images:java_21',
                'Java 17' => 'ghcr.io/skyportsh/images:java_17',
            ],
            'features' => ['eula'],
            'config_files' => '{"server.properties":{"parser":"properties","find":{"server-ip":"0.0.0.0","server-port":"{{server.build.default.port}}","query.port":"{{server.build.default.port}}"}}}',
            'config_startup' => '{"done":") For help, type"}',
            'config_logs' => '{}',
            'config_stop' => 'stop',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
apt update
apt install -y curl jq

cd /mnt/server

if [ -z "${MINECRAFT_VERSION}" ] || [ "${MINECRAFT_VERSION}" == "latest" ]; then
    MINECRAFT_VERSION=$(curl -sSL https://launchermeta.mojang.com/mc/game/version_manifest.json | jq -r '.latest.release')
fi

MANIFEST_URL=$(curl -sSL https://launchermeta.mojang.com/mc/game/version_manifest.json | jq -r --arg V "${MINECRAFT_VERSION}" '.versions[] | select(.id==$V) | .url')

if [ -z "${MANIFEST_URL}" ] || [ "${MANIFEST_URL}" == "null" ]; then
    echo "Could not resolve Minecraft version ${MINECRAFT_VERSION}."
    exit 1
fi

DOWNLOAD_URL=$(curl -sSL "${MANIFEST_URL}" | jq -r '.downloads.server.url')
curl -o "${SERVER_JARFILE}" "${DOWNLOAD_URL}"
echo "eula=true" > eula.txt
BASH,
            'variables' => [
                [
                    'name' => 'Server Jar File',
                    'description' => 'Filename the server jar is saved to.',
                    'env_variable' => 'SERVER_JARFILE',
                    'default_value' => 'server.jar',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|regex:/^([\w\d._-]+)(\.jar)$/',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Minecraft Version',
                    'description' => 'Vanilla version to install. Use "latest" for the newest release.',
                    'env_variable' => 'MINECRAFT_VERSION',
                    'default_value' => 'latest',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:32',
                    'field_type' => 'text',
                ],
            ],
        ],

        [
            'key' => 'minecraft-paper',
            'category' => 'minecraft',
            'icon' => 'leaf',
            'name' => 'Minecraft: Paper',
            'author' => 'skyport',
            'description' => 'High-performance Spigot fork with plugin support and aggressive optimisations.',
            'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -Dterminal.jline=false -Dterminal.ansi=true -jar {{SERVER_JARFILE}}',
            'docker_images' => [
                'Java 21' => 'ghcr.io/skyportsh/images:java_21',
                'Java 17' => 'ghcr.io/skyportsh/images:java_17',
            ],
            'features' => ['eula'],
            'config_files' => '{"server.properties":{"parser":"properties","find":{"server-ip":"0.0.0.0","server-port":"{{server.build.default.port}}","query.port":"{{server.build.default.port}}"}}}',
            'config_startup' => '{"done":") For help, type"}',
            'config_logs' => '{}',
            'config_stop' => 'stop',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
apt update
apt install -y curl jq

cd /mnt/server

if [ -z "${MINECRAFT_VERSION}" ] || [ "${MINECRAFT_VERSION}" == "latest" ]; then
    MINECRAFT_VERSION=$(curl -sSL https://api.papermc.io/v2/projects/paper | jq -r '.versions[-1]')
fi

if [ -z "${BUILD_NUMBER}" ] || [ "${BUILD_NUMBER}" == "latest" ]; then
    BUILD_NUMBER=$(curl -sSL "https://api.papermc.io/v2/projects/paper/versions/${MINECRAFT_VERSION}/builds" | jq -r '.builds[-1].build')
fi

JAR_NAME="paper-${MINECRAFT_VERSION}-${BUILD_NUMBER}.jar"
curl -o "${SERVER_JARFILE}" "https://api.papermc.io/v2/projects/paper/versions/${MINECRAFT_VERSION}/builds/${BUILD_NUMBER}/downloads/${JAR_NAME}"
echo "eula=true" > eula.txt
BASH,
            'variables' => [
                [
                    'name' => 'Server Jar File',
                    'description' => 'Filename the Paper jar is saved to.',
                    'env_variable' => 'SERVER_JARFILE',
                    'default_value' => 'server.jar',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|regex:/^([\w\d._-]+)(\.jar)$/',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Minecraft Version',
                    'description' => 'Paper Minecraft version. Use "latest" for the newest available.',
                    'env_variable' => 'MINECRAFT_VERSION',
                    'default_value' => 'latest',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:32',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Build Number',
                    'description' => 'Specific Paper build number, or "latest".',
                    'env_variable' => 'BUILD_NUMBER',
                    'default_value' => 'latest',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:32',
                    'field_type' => 'text',
                ],
            ],
        ],

        [
            'key' => 'minecraft-forge',
            'category' => 'minecraft',
            'icon' => 'hammer',
            'name' => 'Minecraft: Forge',
            'author' => 'skyport',
            'description' => 'Forge mod-loader server. Drop mods into /mods after install.',
            'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -Dterminal.jline=false -Dterminal.ansi=true @user_jvm_args.txt @libraries/net/minecraftforge/forge/{{MINECRAFT_VERSION}}-{{FORGE_VERSION}}/unix_args.txt nogui',
            'docker_images' => [
                'Java 21' => 'ghcr.io/skyportsh/images:java_21',
                'Java 17' => 'ghcr.io/skyportsh/images:java_17',
            ],
            'features' => ['eula'],
            'config_files' => '{"server.properties":{"parser":"properties","find":{"server-ip":"0.0.0.0","server-port":"{{server.build.default.port}}","query.port":"{{server.build.default.port}}"}}}',
            'config_startup' => '{"done":") For help, type"}',
            'config_logs' => '{}',
            'config_stop' => 'stop',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
apt update
apt install -y curl jq openjdk-17-jre-headless

cd /mnt/server

INSTALLER_URL="https://maven.minecraftforge.net/net/minecraftforge/forge/${MINECRAFT_VERSION}-${FORGE_VERSION}/forge-${MINECRAFT_VERSION}-${FORGE_VERSION}-installer.jar"
curl -o forge-installer.jar "${INSTALLER_URL}"
java -jar forge-installer.jar --installServer
rm -f forge-installer.jar forge-installer.jar.log
echo "eula=true" > eula.txt
BASH,
            'variables' => [
                [
                    'name' => 'Minecraft Version',
                    'description' => 'Minecraft base version (e.g. 1.20.1).',
                    'env_variable' => 'MINECRAFT_VERSION',
                    'default_value' => '1.20.1',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:32',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Forge Version',
                    'description' => 'Forge build for the selected Minecraft version (e.g. 47.2.20).',
                    'env_variable' => 'FORGE_VERSION',
                    'default_value' => '47.2.20',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:32',
                    'field_type' => 'text',
                ],
            ],
        ],

        // ─── Games ─────────────────────────────────────────────────────────

        [
            'key' => 'game-rust',
            'category' => 'game',
            'icon' => 'flame',
            'name' => 'Rust',
            'author' => 'skyport',
            'description' => 'Facepunch Rust dedicated server, installed via SteamCMD.',
            'startup' => './RustDedicated -batchmode +server.port {{SERVER_PORT}} +server.identity "rust" +rcon.port {{RCON_PORT}} +rcon.web true +server.hostname "{{HOSTNAME}}" +server.level "{{LEVEL}}" +server.description "{{DESCRIPTION}}" +server.url "{{SERVER_URL}}" +server.headerimage "{{SERVER_IMG}}" +server.maxplayers {{MAX_PLAYERS}} +rcon.password "{{RCON_PASS}}" +server.saveinterval {{SAVEINTERVAL}} +app.port {{APP_PORT}}',
            'docker_images' => [
                'Rust' => 'ghcr.io/skyportsh/games:rust',
            ],
            'features' => [],
            'config_files' => '{}',
            'config_startup' => '{"done":"Server startup complete"}',
            'config_logs' => '{}',
            'config_stop' => 'quit',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
apt update
apt install -y curl tar lib32gcc-s1

mkdir -p /mnt/server/steamcmd
cd /mnt/server/steamcmd
curl -sSL https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz | tar -xz

./steamcmd.sh +force_install_dir /mnt/server +login anonymous +app_update 258550 validate +quit
BASH,
            'variables' => [
                [
                    'name' => 'Hostname',
                    'description' => 'Server name shown in the in-game browser.',
                    'env_variable' => 'HOSTNAME',
                    'default_value' => 'A Skyport Rust Server',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:64',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Level',
                    'description' => 'Map procedural generation seed/level.',
                    'env_variable' => 'LEVEL',
                    'default_value' => 'Procedural Map',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:64',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Max Players',
                    'description' => 'Maximum players allowed online at once.',
                    'env_variable' => 'MAX_PLAYERS',
                    'default_value' => '40',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|integer|min:1|max:512',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'RCON Password',
                    'description' => 'Password for RCON access.',
                    'env_variable' => 'RCON_PASS',
                    'default_value' => 'CHANGEME',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|min:6|max:64',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'RCON Port',
                    'description' => 'Port the RCON listener binds to.',
                    'env_variable' => 'RCON_PORT',
                    'default_value' => '28016',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|integer|min:1024|max:65535',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'App Port',
                    'description' => 'Rust+ companion app port.',
                    'env_variable' => 'APP_PORT',
                    'default_value' => '28082',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|integer|min:1024|max:65535',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Save Interval',
                    'description' => 'Seconds between automatic world saves.',
                    'env_variable' => 'SAVEINTERVAL',
                    'default_value' => '300',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|integer|min:60|max:3600',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Description',
                    'description' => 'Long-form description displayed in the server browser.',
                    'env_variable' => 'DESCRIPTION',
                    'default_value' => 'Powered by Skyport',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'nullable|string|max:255',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Server URL',
                    'description' => 'Optional homepage URL.',
                    'env_variable' => 'SERVER_URL',
                    'default_value' => 'https://skyport.dev',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'nullable|url|max:255',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Header Image',
                    'description' => 'URL to a 512x256 PNG header image.',
                    'env_variable' => 'SERVER_IMG',
                    'default_value' => '',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'nullable|url|max:255',
                    'field_type' => 'text',
                ],
            ],
        ],

        [
            'key' => 'game-garrysmod',
            'category' => 'game',
            'icon' => 'puzzle',
            'name' => "Garry's Mod",
            'author' => 'skyport',
            'description' => 'Source-engine sandbox. Installs the Garry\'s Mod dedicated server (app 4020) via SteamCMD.',
            'startup' => './srcds_run -game garrysmod -console -port {{SERVER_PORT}} +map {{SRCDS_MAP}} +maxplayers {{MAX_PLAYERS}} +gamemode {{GAMEMODE}}',
            'docker_images' => [
                'Source' => 'ghcr.io/skyportsh/games:source',
            ],
            'features' => [],
            'config_files' => '{}',
            'config_startup' => '{"done":"VAC secure mode is activated"}',
            'config_logs' => '{}',
            'config_stop' => 'quit',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
apt update
apt install -y curl tar lib32gcc-s1 lib32stdc++6

mkdir -p /mnt/server/steamcmd
cd /mnt/server/steamcmd
curl -sSL https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz | tar -xz

./steamcmd.sh +force_install_dir /mnt/server +login anonymous +app_update 4020 validate +quit
BASH,
            'variables' => [
                [
                    'name' => 'Map',
                    'description' => 'Initial map to load.',
                    'env_variable' => 'SRCDS_MAP',
                    'default_value' => 'gm_construct',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:64',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Max Players',
                    'description' => 'Maximum number of players.',
                    'env_variable' => 'MAX_PLAYERS',
                    'default_value' => '32',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|integer|min:1|max:128',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Gamemode',
                    'description' => 'Initial gamemode (sandbox, ttt, darkrp, …).',
                    'env_variable' => 'GAMEMODE',
                    'default_value' => 'sandbox',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:32',
                    'field_type' => 'text',
                ],
            ],
        ],

        [
            'key' => 'game-tf2',
            'category' => 'game',
            'icon' => 'crosshair',
            'name' => 'Team Fortress 2',
            'author' => 'skyport',
            'description' => 'Team Fortress 2 dedicated server (app 232250) via SteamCMD.',
            'startup' => './srcds_run -game tf -console -port {{SERVER_PORT}} +map {{SRCDS_MAP}} +maxplayers {{MAX_PLAYERS}}',
            'docker_images' => [
                'Source' => 'ghcr.io/skyportsh/games:source',
            ],
            'features' => [],
            'config_files' => '{}',
            'config_startup' => '{"done":"VAC secure mode is activated"}',
            'config_logs' => '{}',
            'config_stop' => 'quit',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
apt update
apt install -y curl tar lib32gcc-s1 lib32stdc++6

mkdir -p /mnt/server/steamcmd
cd /mnt/server/steamcmd
curl -sSL https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz | tar -xz

./steamcmd.sh +force_install_dir /mnt/server +login anonymous +app_update 232250 validate +quit
BASH,
            'variables' => [
                [
                    'name' => 'Map',
                    'description' => 'Initial map to load.',
                    'env_variable' => 'SRCDS_MAP',
                    'default_value' => 'cp_badlands',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:64',
                    'field_type' => 'text',
                ],
                [
                    'name' => 'Max Players',
                    'description' => 'Maximum number of players.',
                    'env_variable' => 'MAX_PLAYERS',
                    'default_value' => '24',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|integer|min:1|max:64',
                    'field_type' => 'text',
                ],
            ],
        ],

        [
            'key' => 'game-hytale',
            'category' => 'game',
            'icon' => 'gem',
            'name' => 'Hytale',
            'author' => 'skyport',
            'description' => 'Hytale runtime image scaffold. Drop your build into /mnt/server after install.',
            'startup' => './start.sh',
            'docker_images' => [
                'Hytale' => 'ghcr.io/skyportsh/games:hytale',
            ],
            'features' => [],
            'config_files' => '{}',
            'config_startup' => '{"done":"Server ready"}',
            'config_logs' => '{}',
            'config_stop' => 'stop',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
cd /mnt/server
cat > start.sh <<'SH'
#!/bin/bash
echo "Hytale starter — drop your build into /mnt/server then customise this start.sh"
sleep infinity
SH
chmod +x start.sh
BASH,
            'variables' => [],
        ],

        // ─── Runtimes ──────────────────────────────────────────────────────

        [
            'key' => 'runtime-nodejs',
            'category' => 'runtime',
            'icon' => 'terminal',
            'name' => 'Node.js Generic',
            'author' => 'skyport',
            'description' => 'Generic Node.js runtime. Provide an entrypoint file and any deps your app needs.',
            'startup' => '/usr/local/bin/node /mnt/server/{{NODE_ENTRY}}',
            'docker_images' => [
                'Node 20' => 'ghcr.io/skyportsh/images:nodejs_20',
                'Node 18' => 'ghcr.io/skyportsh/images:nodejs_18',
            ],
            'features' => [],
            'config_files' => '{}',
            'config_startup' => '{"done":"listening on"}',
            'config_logs' => '{}',
            'config_stop' => '^C',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
cd /mnt/server
cat > index.js <<'JS'
const port = process.env.SERVER_PORT || 3000;
const http = require('http');
http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Hello from Skyport Node.js generic\n');
}).listen(port, '0.0.0.0', () => console.log('listening on ' + port));
JS
BASH,
            'variables' => [
                [
                    'name' => 'Entrypoint',
                    'description' => 'JavaScript file to execute on start.',
                    'env_variable' => 'NODE_ENTRY',
                    'default_value' => 'index.js',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:128',
                    'field_type' => 'text',
                ],
            ],
        ],

        [
            'key' => 'runtime-python',
            'category' => 'runtime',
            'icon' => 'terminal',
            'name' => 'Python Generic',
            'author' => 'skyport',
            'description' => 'Generic Python runtime. Provide a script and optional requirements.txt.',
            'startup' => 'python3 /mnt/server/{{PY_ENTRY}}',
            'docker_images' => [
                'Python 3.11' => 'ghcr.io/skyportsh/images:python_3.11',
                'Python 3.10' => 'ghcr.io/skyportsh/images:python_3.10',
            ],
            'features' => [],
            'config_files' => '{}',
            'config_startup' => '{"done":"Running on"}',
            'config_logs' => '{}',
            'config_stop' => '^C',
            'install_container' => 'ghcr.io/skyportsh/installers:debian',
            'install_entrypoint' => 'bash',
            'install_script' => <<<'BASH'
#!/bin/bash
cd /mnt/server
cat > main.py <<'PY'
import os
from http.server import BaseHTTPRequestHandler, HTTPServer

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b'Hello from Skyport Python generic\n')

port = int(os.environ.get('SERVER_PORT', '8000'))
print(f'Running on 0.0.0.0:{port}')
HTTPServer(('0.0.0.0', port), Handler).serve_forever()
PY
BASH,
            'variables' => [
                [
                    'name' => 'Entrypoint',
                    'description' => 'Python file to execute on start.',
                    'env_variable' => 'PY_ENTRY',
                    'default_value' => 'main.py',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => 'required|string|max:128',
                    'field_type' => 'text',
                ],
            ],
        ],

    ],
];
