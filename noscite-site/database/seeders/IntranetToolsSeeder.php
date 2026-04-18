<?php

namespace Database\Seeders;

use App\Models\IntranetTool;
use Illuminate\Database\Seeder;

class IntranetToolsSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            ['section' => 'AI Tools', 'type' => 'tool', 'icon' => '✦', 'name' => 'Claude', 'description' => 'AI assistant principale Noscite. Usare per: analisi, scrittura, coding, ragionamento complesso, email clienti.', 'url' => 'https://claude.ai', 'label' => 'claude.ai', 'active' => true, 'sort_order' => 1],
            ['section' => 'AI Tools', 'type' => 'tool', 'icon' => '🔍', 'name' => 'Perplexity AI', 'description' => 'Ricerca verificata con fonti. Usare per: ricerche di mercato, dati aggiornati, notizie, fact-checking.', 'url' => 'https://perplexity.ai', 'label' => 'perplexity.ai', 'active' => true, 'sort_order' => 2],
            ['section' => 'AI Tools', 'type' => 'tool', 'icon' => '🤖', 'name' => 'ChatGPT', 'description' => 'Workflow: Perplexity→Claude→ChatGPT. Usare per: output formattati, DALL-E, GPT-4o vision.', 'url' => 'https://chatgpt.com', 'label' => 'chatgpt.com', 'active' => true, 'sort_order' => 3],
            ['section' => 'Microsoft 365', 'type' => 'tool', 'icon' => '📞', 'name' => 'Teams', 'description' => 'Comunicazione interna, videocall clienti, canali per progetto.', 'url' => 'https://teams.microsoft.com', 'label' => 'teams.microsoft.com', 'active' => true, 'sort_order' => 4],
            ['section' => 'Microsoft 365', 'type' => 'tool', 'icon' => '📧', 'name' => 'Outlook', 'description' => 'Email aziendale @noscite.it, calendario, contatti.', 'url' => 'https://outlook.office.com', 'label' => 'outlook.office.com', 'active' => true, 'sort_order' => 5],
            ['section' => 'Microsoft 365', 'type' => 'tool', 'icon' => '📁', 'name' => 'SharePoint / OneDrive', 'description' => 'Documenti condivisi, materiali corsi, contratti, archivio aziendale.', 'url' => 'https://noscite.sharepoint.com', 'label' => 'sharepoint', 'active' => true, 'sort_order' => 6],
            ['section' => 'Strumenti Noscite', 'type' => 'tool', 'icon' => '🎓', 'name' => 'Atheneum Admin', 'description' => 'Gestione corsi, studenti, quiz, RAG e analytics della piattaforma formativa.', 'url' => 'https://atheneum.noscite.it/admin', 'label' => 'atheneum.noscite.it/admin', 'active' => true, 'sort_order' => 7],
            ['section' => 'Strumenti Noscite', 'type' => 'tool', 'icon' => '🌐', 'name' => 'Sito Admin', 'description' => 'Gestione blog Commentarium, contenuti e impostazioni del sito noscite.it.', 'url' => 'https://noscite.it/nosciteadmin', 'label' => 'noscite.it/nosciteadmin', 'active' => true, 'sort_order' => 8],
            ['section' => 'Strumenti Noscite', 'type' => 'tool', 'icon' => '⚡', 'name' => 'MCPHub', 'description' => 'Server MCP aziendale per agenti AI. Endpoint per integrazione con Claude e altri LLM.', 'url' => 'https://mcphub.noscite.it', 'label' => 'mcphub.noscite.it', 'active' => true, 'sort_order' => 9],
            ['section' => 'POC', 'type' => 'poc', 'icon' => '🎓', 'name' => 'Atheneum — Demo studente', 'description' => 'Piattaforma formativa completa con corsi AI, quiz interattivi, chatbot Minerva RAG e certificazioni PDF.', 'url' => 'https://atheneum.noscite.it/learn/login?demo=1', 'credentials' => 'demo@atheneum.noscite.it · Demo2024', 'status' => 'LIVE', 'active' => true, 'sort_order' => 1],
            ['section' => 'POC', 'type' => 'poc', 'icon' => '⚡', 'name' => 'MCPHub — Agenti AI', 'description' => 'Server MCP in produzione. Dimostra integrazione agenti AI con sistemi aziendali tramite protocollo MCP.', 'url' => 'https://mcphub.noscite.it', 'status' => 'LIVE', 'active' => true, 'sort_order' => 2],
        ];

        foreach ($tools as $tool) {
            IntranetTool::firstOrCreate(
                ['name' => $tool['name'], 'type' => $tool['type']],
                $tool
            );
        }
    }
}
