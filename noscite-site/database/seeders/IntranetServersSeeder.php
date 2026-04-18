<?php

namespace Database\Seeders;

use App\Models\IntranetServer;
use Illuminate\Database\Seeder;

class IntranetServersSeeder extends Seeder
{
    public function run(): void
    {
        $servers = [
            [
                'name' => 'Kalendarium',
                'hostname' => 'vps-e9b80810.vps.ovh.net',
                'ip_address' => '91.134.242.201',
                'url' => 'https://calenda.noscite.it',
                'provider' => 'OVH',
                'github_url' => 'https://github.com/Noscitedevteam/Calendario_editoriale',
                'service' => 'Calendario editoriale Noscite',
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'name' => 'Assessment',
                'hostname' => 'NewAssessment',
                'ip_address' => '94.177.163.112',
                'url' => 'https://newassessment.noscite.it',
                'provider' => 'ARUBA',
                'github_url' => 'https://github.com/Noscitedevteam/assessment',
                'service' => 'Piattaforma assessment clienti',
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'name' => 'CRM',
                'hostname' => 'vps-8334f5b7.vps.ovh.net',
                'ip_address' => '51.254.142.212',
                'url' => 'https://crm.noscite.it',
                'provider' => 'OVH',
                'github_url' => 'https://github.com/Noscitedevteam/Noscite_ecosystem',
                'service' => 'CRM Noscite',
                'status' => 'active',
                'sort_order' => 3,
            ],
            [
                'name' => 'Gubernator',
                'hostname' => 'Gubernator',
                'ip_address' => '80.211.137.71',
                'url' => 'https://gubernator.noscite.it',
                'provider' => 'ARUBA',
                'github_url' => 'https://github.com/Noscitedevteam/Gubernator',
                'service' => 'Gubernator',
                'status' => 'active',
                'sort_order' => 4,
            ],
            [
                'name' => 'Atheneum + Noscite.it',
                'hostname' => 'vps-ec14e0ef.vps.ovh.net',
                'ip_address' => '135.125.254.253',
                'url' => 'https://atheneum.noscite.it',
                'provider' => 'OVH',
                'github_url' => 'https://github.com/Noscitedevteam/websites',
                'service' => 'Piattaforma formativa Atheneum + sito noscite.it',
                'status' => 'active',
                'os' => 'Ubuntu 25.04',
                'specs' => '6 CPU · 11GB RAM · 96GB SSD',
                'sort_order' => 5,
            ],
            [
                'name' => 'MCP Hub',
                'hostname' => 'powerMCP',
                'ip_address' => '93.186.254.139',
                'url' => 'https://mcpgate.it',
                'provider' => 'ARUBA',
                'github_url' => 'https://github.com/Noscitedevteam/mcp-hub.saas',
                'service' => 'Server MCP agenti AI',
                'status' => 'active',
                'sort_order' => 6,
            ],
        ];

        foreach ($servers as $server) {
            IntranetServer::firstOrCreate(
                ['name' => $server['name']],
                $server
            );
        }
    }
}
