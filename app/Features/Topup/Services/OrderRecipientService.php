<?php

namespace App\Features\Topup\Services;

use App\Features\Topup\Support\RecipientFieldPattern;
use App\Models\Game;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderRecipientService
{
    public const MAX_RECIPIENTS = 100;

    public const MAX_QUANTITY_PER_RECIPIENT = 10;

    public function __construct(private readonly RecipientFieldPattern $recipientFieldPattern) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     mode: 'single'|'bulk', quantity: int, quantity_field: string,
     *     fields: array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string}>,
     *     recipients: array<int, array{data:array<string, string>,quantity:int}>,
     *     game_account: string, game_character: string|null
     * }
     */
    public function resolve(Game $game, array $payload): array
    {
        $fields = $game->checkoutFields();
        $mode = ($payload['purchase_mode'] ?? 'single') === 'bulk' ? 'bulk' : 'single';

        if (array_key_exists('api_recipients', $payload)) {
            $recipients = $this->parseApiRecipients($payload['api_recipients'], $fields);
            $quantity = array_sum(array_column($recipients, 'quantity'));
            $quantityField = 'recipients';
            $mode = count($recipients) > 1 ? 'bulk' : 'single';
        } elseif ($mode === 'bulk') {
            $recipients = $this->parseBulkRecipients((string) ($payload['bulk_recipients'] ?? ''), $fields);
            $quantity = array_sum(array_column($recipients, 'quantity'));
            $quantityField = 'bulk_recipients';
        } else {
            $quantity = (int) ($payload['single_quantity'] ?? 1);
            $recipients = [[
                'data' => $this->normalizeRecipient($payload['recipient_fields'] ?? [], $fields, 'recipient_fields'),
                'quantity' => $quantity,
            ]];
            $quantityField = 'single_quantity';
        }

        $firstRecipient = $recipients[0]['data'];
        $primaryValue = collect($fields)
            ->map(fn (array $field): string => $firstRecipient[$field['key']] ?? '')
            ->first(fn (string $value): bool => $value !== '');

        return [
            'mode' => $mode,
            'quantity' => $quantity,
            'quantity_field' => $quantityField,
            'fields' => $fields,
            'recipients' => $recipients,
            'game_account' => $firstRecipient['game_account'] ?? $primaryValue ?? 'recipient-1',
            'game_character' => filled($firstRecipient['game_character'] ?? null)
                ? $firstRecipient['game_character']
                : null,
        ];
    }

    /**
     * @param  array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string}>  $fields
     * @return array<int, array{data:array<string, string>,quantity:int}>
     */
    private function parseApiRecipients(mixed $input, array $fields): array
    {
        if (! is_array($input) || $input === []) {
            throw ValidationException::withMessages(['recipients' => 'Cần ít nhất một tài khoản nhận.']);
        }

        if (count($input) > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages([
                'recipients' => 'Mỗi task chỉ được có tối đa '.self::MAX_RECIPIENTS.' tài khoản nhận.',
            ]);
        }

        return collect($input)
            ->values()
            ->map(function (mixed $item, int $index) use ($fields): array {
                if (! is_array($item)) {
                    throw ValidationException::withMessages([
                        "recipients.{$index}" => 'Thông tin tài khoản nhận không hợp lệ.',
                    ]);
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                if ($quantity < 1 || $quantity > self::MAX_QUANTITY_PER_RECIPIENT) {
                    throw ValidationException::withMessages([
                        "recipients.{$index}.quantity" => 'Số lượng phải từ 1 đến '.self::MAX_QUANTITY_PER_RECIPIENT.'.',
                    ]);
                }

                return [
                    'data' => $this->normalizeRecipient(
                        $item['data'] ?? null,
                        $fields,
                        "recipients.{$index}.data",
                        $index + 1,
                    ),
                    'quantity' => $quantity,
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string}>  $fields
     * @return array<int, array{data:array<string, string>,quantity:int}>
     */
    private function parseBulkRecipients(string $input, array $fields): array
    {
        $normalizedInput = ltrim($input, "\xEF\xBB\xBF");
        $lines = collect(preg_split('/\R/u', trim($normalizedInput)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'bulk_recipients' => 'Vui lòng nhập ít nhất một dòng thông tin tài khoản.',
            ]);
        }

        if ($lines->count() > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages([
                'bulk_recipients' => 'Mỗi đơn chỉ được nhập tối đa '.self::MAX_RECIPIENTS.' tài khoản.',
            ]);
        }

        return $lines
            ->map(function (string $line, int $index) use ($fields): array {
                $values = explode('|', $line);
                $expectedColumnCount = count($fields) + 1;
                $accountIdentifier = trim((string) ($values[0] ?? ''));

                if (! str_contains($line, '|') || $accountIdentifier === '') {
                    throw ValidationException::withMessages([
                        'bulk_recipients' => $this->invalidBulkFormatMessage($accountIdentifier, $index + 1),
                    ]);
                }

                if (count($values) > $expectedColumnCount) {
                    throw ValidationException::withMessages([
                        'bulk_recipients' => $this->invalidBulkFormatMessage($accountIdentifier, $index + 1),
                    ]);
                }

                $quantityValue = trim((string) array_pop($values));
                if (preg_match('/^[1-9]\d*$/D', $quantityValue) !== 1) {
                    throw ValidationException::withMessages([
                        'bulk_recipients' => $this->invalidBulkFormatMessage($accountIdentifier, $index + 1),
                    ]);
                }

                $quantity = (int) $quantityValue;
                if ($quantity > self::MAX_QUANTITY_PER_RECIPIENT) {
                    throw ValidationException::withMessages([
                        'bulk_recipients' => 'Số lượng thẻ ở dòng '.($index + 1).' không được vượt quá '.self::MAX_QUANTITY_PER_RECIPIENT.'.',
                    ]);
                }

                $values = array_pad($values, count($fields), '');

                return [
                    'data' => $this->normalizeRecipient(
                        array_combine(collect($fields)->pluck('key')->all(), $values) ?: [],
                        $fields,
                        'bulk_recipients',
                        $index + 1,
                    ),
                    'quantity' => $quantity,
                ];
            })
            ->all();
    }

    private function invalidBulkFormatMessage(string $accountIdentifier, int $lineNumber): string
    {
        $identifier = $accountIdentifier !== '' ? Str::limit($accountIdentifier, 80, '…') : 'ở dòng '.$lineNumber;

        return "Tài khoản {$identifier} định dạng không hợp lệ. Vui lòng nhập đúng định dạng param|số lượng.";
    }

    /**
     * @param  array<int, array{key:string,label:string,placeholder:string,required:bool,regex:string}>  $fields
     * @return array<string, string>
     */
    private function normalizeRecipient(mixed $input, array $fields, string $errorKey, ?int $lineNumber = null): array
    {
        if (! is_array($input)) {
            throw ValidationException::withMessages([$errorKey => 'Thông tin tài khoản không hợp lệ.']);
        }

        $allowedKeys = collect($fields)->pluck('key')->all();
        if (array_diff(array_keys($input), $allowedKeys) !== []) {
            throw ValidationException::withMessages([$errorKey => 'Thông tin chứa trường không được cấu hình cho game này.']);
        }

        $recipient = [];
        foreach ($fields as $fieldIndex => $field) {
            $value = Str::lower(trim((string) ($input[$field['key']] ?? '')));
            $location = $lineNumber === null ? '' : ' ở dòng '.$lineNumber;

            if ($field['required'] && $value === '') {
                throw ValidationException::withMessages([
                    $errorKey => "{$field['label']}{$location} không được để trống.",
                ]);
            }

            if (Str::length($value) > 191) {
                throw ValidationException::withMessages([
                    $errorKey => "{$field['label']}{$location} không được vượt quá 191 ký tự.",
                ]);
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                throw ValidationException::withMessages([
                    $errorKey => "{$field['label']}{$location} chứa ký tự điều khiển không hợp lệ.",
                ]);
            }

            $regex = (string) ($field['regex'] ?? '');
            if ($value !== '' && $regex !== '' && ! $this->recipientFieldPattern->matches($regex, $value)) {
                $validationErrorKey = $errorKey === 'bulk_recipients'
                    ? $errorKey
                    : $errorKey.'.'.$field['key'];
                $column = $lineNumber === null ? '' : ', cột '.($fieldIndex + 1);

                throw ValidationException::withMessages([
                    $validationErrorKey => "{$field['label']}{$location}{$column} không đúng định dạng.",
                ]);
            }

            $recipient[$field['key']] = $value;
        }

        return $recipient;
    }
}
