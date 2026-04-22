<?php

declare(strict_types=1);

namespace App\Services\GoTo\Reports;

use Generator;

/**
 * Service for Call Events Report Summaries.
 */
class CallEventsSummaryReportService extends BaseReportService
{
    protected string $reportName = 'call_events_summary';
    protected string $endpoint;

    public function __construct($apiClient)
    {
        parent::__construct($apiClient);
        $this->endpoint = config('goto.endpoints.call_events_summaries');
    }

    /**
     * Get call events summary data.
     */
    public function getData(array $params = []): Generator
    {
        $query = $this->buildDateRangeParams($params);
        yield from $this->apiClient->getPaginated($this->endpoint, $query);
    }

    /**
     * Get CSV headers for call events summary report.
     *
     * Mirrors GoTo Admin Portal's native "call-report-conversations" export so
     * the file is a drop-in replacement, plus exposes the rich Participants
     * column (combined into one cell) that the simple Call History API lacks.
     */
    public function getCsvHeaders(): array
    {
        return [
            'Date',
            'Answer time',
            'End time',
            'Duration [Milliseconds]',
            'Duration',
            'Direction',
            'Call Result',
            'From',
            'Participants',
            'Recordings',
            'Voicemails',
            'Conversation space id',
            'Call Provider',
            'Caller Outcome',
            'Call Initiator',
        ];
    }

    /**
     * Transform a single Call Events Report Summary row into CSV format.
     */
    public function transformRow(array $row): array
    {
        $created  = (string) $this->getNestedValue($row, 'callCreated', '');
        $answered = (string) $this->getNestedValue($row, 'callAnswered', '');
        $ended    = (string) $this->getNestedValue($row, 'callEnded', '');

        $durationMs = $this->msBetween($created, $ended);

        $participants = $row['participants'] ?? [];
        $participantsStr = $this->formatParticipants($row);

        return [
            $created,
            $answered,
            $ended,
            $durationMs !== null ? (string) $durationMs : '',
            $durationMs !== null ? $this->formatDuration((int) floor($durationMs / 1000)) : '',
            $this->mapDirection((string) $this->getNestedValue($row, 'direction', '')),
            $this->mapCallerOutcome((string) $this->getNestedValue($row, 'callerOutcome', '')),
            $this->getNestedValue($row, 'caller.number', ''),
            $participantsStr,
            $this->participantsHaveRecordings($participants) ? 'true' : 'false',
            $this->participantsHaveVoicemails($participants) ? 'true' : 'false',
            $this->getNestedValue($row, 'conversationSpaceId', ''),
            $this->getNestedValue($row, 'caller.type.callProvider', ''),
            $this->getNestedValue($row, 'callerOutcome', ''),
            $this->getNestedValue($row, 'callInitiator', ''),
        ];
    }

    /**
     * Build the combined Participants column to mirror the GoTo Admin Portal
     * "call-report-conversations" export, which lists EVERY entity involved in
     * the conversation joined by `;`.
     *
     * Format per entity: `<extension-or-number>: <name>` (or just the
     * identifier when no name is available). Order matches the platform:
     *   1. internal participants[] (lines/extensions that handled the call)
     *   2. the dialed DID  (caller.type.number / caller.type.name)
     *   3. the external caller number (caller.number)
     *
     * Duplicates are removed so the same number doesn't appear twice when the
     * caller already shows up inside participants[].
     */
    private function formatParticipants(array $row): string
    {
        $entries = [];

        // 1. Internal participants[] in the order GoTo returns them.
        foreach ($row['participants'] ?? [] as $p) {
            $entries[] = $this->participantEntry(
                name: (string) ($p['name'] ?? ''),
                number: (string) ($p['number'] ?? ''),
                extension: (string) ($p['type']['extensionNumber'] ?? '')
            );
        }

        // 2. The dialed DID (the company-side phone number on which the call landed).
        $callerType = $row['caller']['type'] ?? [];
        $entries[] = $this->participantEntry(
            name: (string) ($callerType['name'] ?? ''),
            number: (string) ($callerType['number'] ?? ''),
            extension: (string) ($callerType['extensionNumber'] ?? '')
        );

        // 3. The external caller number (no name, matches the platform output).
        $entries[] = $this->participantEntry(
            name: '',
            number: (string) ($row['caller']['number'] ?? ''),
            extension: ''
        );

        // Drop empties + dedupe while preserving order.
        $seen = [];
        $out = [];
        foreach ($entries as $e) {
            if ($e === '' || isset($seen[$e])) {
                continue;
            }
            $seen[$e] = true;
            $out[] = $e;
        }

        return implode(';', $out);
    }

    private function participantEntry(string $name, string $number, string $extension): string
    {
        $identifier = $extension !== '' ? $extension : $number;
        $name = trim($name);

        if ($identifier === '' && $name === '') {
            return '';
        }
        if ($identifier !== '' && $name !== '') {
            return $identifier . ': ' . $name;
        }
        return $identifier !== '' ? $identifier : $name;
    }

    private function participantsHaveRecordings(array $participants): bool
    {
        foreach ($participants as $p) {
            if (!empty($p['recording']['id'])) {
                return true;
            }
        }
        return false;
    }

    private function participantsHaveVoicemails(array $participants): bool
    {
        foreach ($participants as $p) {
            $outcome = strtoupper((string) ($p['outcome'] ?? ''));
            if ($outcome === 'VOICEMAIL') {
                return true;
            }
        }
        return false;
    }

    private function mapDirection(string $direction): string
    {
        return match (strtoupper($direction)) {
            'INBOUND'  => 'Inbound',
            'OUTBOUND' => 'Outbound',
            default    => $direction,
        };
    }

    private function mapCallerOutcome(string $outcome): string
    {
        return match (strtoupper($outcome)) {
            'NORMAL'             => 'Normal',
            'MISSED'             => 'Missed',
            'VOICEMAIL'          => 'Voicemail',
            'LEFT_QUEUE'         => 'Left queue',
            'LEFT_PARKING_SPOT'  => 'Left parking spot',
            'LEFT_ON_HOLD'       => 'Left on hold',
            'LEFT_DIAL_PLAN'     => 'Dial plan call ended',
            'TRUNCATED'          => 'Truncated',
            'UNKNOWN', ''        => '',
            default              => $outcome,
        };
    }

    private function msBetween(string $startIso, string $endIso): ?int
    {
        if ($startIso === '' || $endIso === '') {
            return null;
        }
        try {
            $start = new \DateTimeImmutable($startIso);
            $end   = new \DateTimeImmutable($endIso);
            $diff  = ($end->getTimestamp() - $start->getTimestamp()) * 1000;
            $diff += (int) round(((int) $end->format('u') - (int) $start->format('u')) / 1000);
            return max(0, $diff);
        } catch (\Throwable) {
            return null;
        }
    }
}
