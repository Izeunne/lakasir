<?php

namespace App\Console\Commands;

use App\Services\PosNotificationService;
use Illuminate\Console\Command;

class SendPosNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:notify 
                            {title : The notification title} 
                            {message : The notification message body} 
                            {--outlet=1 : Outlet ID} 
                            {--type=info : Notification type (order, alert, info, warning)} 
                            {--sound=default : Notification sound}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a realtime notification to the POS mobile app';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $title = (string) $this->argument('title');
        $message = (string) $this->argument('message');
        $outletId = (int) $this->option('outlet');
        $type = (string) $this->option('type');
        $sound = (string) $this->option('sound');

        $notif = PosNotificationService::send($title, $message, [
            'outlet_id' => $outletId,
            'type' => $type,
            'sound' => $sound,
            'data' => [
                'sent_by' => 'artisan-console',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $this->info("✅ Notifikasi berhasil dikirim (ID: {$notif->id})");
        $this->line("   Judul: {$notif->title}");
        $this->line("   Pesan: {$notif->message}");
        $this->line("   Status: {$notif->status}");

        return self::SUCCESS;
    }
}

