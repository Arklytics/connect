<?php

declare(strict_types=1);

final class Crm
{
    public static function tableColumns(mysqli $db, string $table): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return [];
        }

        try {
            $stmt = $db->prepare("SHOW COLUMNS FROM `$table`");
            $stmt->execute();
            $result = $stmt->get_result();
            $columns = [];

            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }

            return $columns;
        } catch (Throwable $exception) {
            return [];
        }
    }

    public static function columnExists(mysqli $db, string $table, string $column): bool
    {
        return in_array($column, self::tableColumns($db, $table), true);
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone);
    }

    public static function responseTemperature(?int $responseMinutes): string
    {
        if ($responseMinutes === null || $responseMinutes < 0) {
            return 'cold';
        }

        if ($responseMinutes <= 120) {
            return 'hot';
        }

        if ($responseMinutes <= 1440) {
            return 'warm';
        }

        return 'cold';
    }

    public static function temperatureLabel(string $temperature): string
    {
        return match ($temperature) {
            'hot' => 'Hot',
            'warm' => 'Warm',
            default => 'Cold',
        };
    }

    public static function noResponseSequence(string $name, string $baseMessage = ''): array
    {
        $greeting = $name !== '' ? "Hi {$name}," : 'Hi there,';
        $baseMessage = trim($baseMessage);

        return [
            [
                'step_no' => 1,
                'delay_days' => 2,
                'message' => $greeting . ' just checking in on my last message. If you still need help, I am here.',
            ],
            [
                'step_no' => 2,
                'delay_days' => 5,
                'message' => $greeting . ' sharing one more helpful note in case you are still considering it. Happy to answer any questions.',
            ],
            [
                'step_no' => 3,
                'delay_days' => 7,
                'message' => $greeting . ' I do not want to crowd your inbox, so I will pause here. If you want to continue later, just reply anytime.',
            ],
        ];
    }

    public static function quickReplySequence(string $name): array
    {
        $greeting = $name !== '' ? "Hi {$name}," : 'Hi there,';

        return [
            [
                'step_no' => 1,
                'delay_days' => 0,
                'message' => $greeting . ' thanks for replying so quickly. I will help you with the next step now.',
            ],
            [
                'step_no' => 2,
                'delay_days' => 1,
                'message' => $greeting . ' sending a quick follow-up with the details we discussed.',
            ],
        ];
    }
}
