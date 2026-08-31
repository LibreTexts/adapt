<?php

namespace App\Rules;

use App\Helpers\Accounting;
use Illuminate\Contracts\Validation\Rule;


class HasValidAccountingJournalEntries implements Rule
{

    protected $errors = [];

    /**
     * Helper method to parse amount strings that may contain commas
     *
     * @param mixed $value
     * @return float|null
     */
    protected function parseAmount($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Convert to string and remove commas
        $sanitized = str_replace(',', '', (string) $value);

        // Check if the sanitized value is numeric
        if (!is_numeric($sanitized)) {
            return null;
        }

        return floatval($sanitized);
    }

    /**
     * Determine if the validation passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->errors = [
            'specific' => [],
            'general' => null
        ];

        // If value is a JSON string, decode it
        if (is_string($value)) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors['general'] = 'Invalid JSON format.';
                return false;
            }
        }

        // Check if entries exist and is an array
        if (!isset($value['entries']) || !is_array($value['entries'])) {
            $this->errors['general'] = 'No journal entries provided.';
            return false;
        }

        $entries = $value['entries'];

        // Require at least one entry
        if (count($entries) === 0) {
            $this->errors['general'] = 'At least one journal entry is required.';
            return false;
        }

        $hasErrors = false;

        foreach ($entries as $entryIndex => $entry) {
            $entryErrors = [];

            // Validate entry text
            if (empty($entry['entryText']) || trim($entry['entryText']) === '') {
                $entryErrors['entryText'] = 'Entry text is required.';
                $hasErrors = true;
            }

            // Validate entry description
            if (empty($entry['entryDescription']) || trim($entry['entryDescription']) === '') {
                $entryErrors['entryDescription'] = 'Entry description is required.';
                $hasErrors = true;
            }

            // Validate solution rows
            if (!isset($entry['solutionRows']) || !is_array($entry['solutionRows'])) {
                $entryErrors['solutionRows'] = ['general' => 'Solution rows are required.'];
                $hasErrors = true;
            } else {
                $solutionRows = $entry['solutionRows'];
                $rowErrors = [];

                // Require at least 2 rows
                if (count($solutionRows) < 2) {
                    $rowErrors['general'] = 'At least 2 solution rows are required.';
                    $hasErrors = true;
                } elseif (count($solutionRows) > 5) {
                    $rowErrors['general'] = 'Maximum of 5 solution rows allowed.';
                    $hasErrors = true;
                }

                $totalDebits = 0;
                $totalCredits = 0;
                $validAccountTitles = Accounting::validAccountingJournalEntries();
                foreach ($solutionRows as $rowIndex => $row) {
                    $rowFieldErrors = [];

                    // Validate account title
                    if (empty($row['accountTitle']) || trim($row['accountTitle']) === '') {
                        $rowFieldErrors['accountTitle'] = 'Account title is required.';
                        $hasErrors = true;
                    } elseif (!in_array($row['accountTitle'], $validAccountTitles)) {
                        $rowFieldErrors['accountTitle'] = 'Account title must be from the valid list of accounts.';
                        $hasErrors = true;
                    }

                    // Validate type
                    if (empty($row['type']) || !in_array($row['type'], ['debit', 'credit'])) {
                        $rowFieldErrors['type'] = 'Type must be either debit or credit.';
                        $hasErrors = true;
                    }

                    // Validate amount - use parseAmount to handle commas
                    $parsedAmount = $this->parseAmount($row['amount'] ?? null);

                    if ($parsedAmount === null) {
                        if (!isset($row['amount']) || $row['amount'] === '' || $row['amount'] === null) {
                            $rowFieldErrors['amount'] = 'Amount is required.';
                        } else {
                            $rowFieldErrors['amount'] = 'Amount must be a valid number.';
                        }
                        $hasErrors = true;
                    } elseif ($parsedAmount < 0) {
                        $rowFieldErrors['amount'] = 'Amount cannot be negative.';
                        $hasErrors = true;
                    } else {
                        // Calculate totals
                        if (isset($row['type'])) {
                            if ($row['type'] === 'debit') {
                                $totalDebits += $parsedAmount;
                            } elseif ($row['type'] === 'credit') {
                                $totalCredits += $parsedAmount;
                            }
                        }
                    }

                    if (!empty($rowFieldErrors)) {
                        $rowErrors[$rowIndex] = $rowFieldErrors;
                    }
                }

                // Check if debits and credits balance (within 0.01 tolerance for floating point)
                if (abs($totalDebits - $totalCredits) > 0.01 && $totalDebits > 0 && $totalCredits > 0) {
                    $rowErrors['general'] = sprintf(
                        'Entry does not balance. Debits: $%.2f, Credits: $%.2f',
                        $totalDebits,
                        $totalCredits
                    );
                    $hasErrors = true;
                }

                if (!empty($rowErrors)) {
                    $entryErrors['solutionRows'] = $rowErrors;
                }
            }

            if (!empty($entryErrors)) {
                $this->errors['specific'][$entryIndex] = $entryErrors;
            }
        }

        // Clean up empty specific errors
        if (empty($this->errors['specific'])) {
            unset($this->errors['specific']);
        }

        // Validate T-Accounts (optional add-on)
        if (!empty($value['includeTAccounts'])) {
            if (!isset($value['tAccounts']) || !is_array($value['tAccounts']) || count($value['tAccounts']) === 0) {
                $this->errors['tAccountsGeneral'] = 'At least one T-Account is required when T-Accounts are enabled.';
                $hasErrors = true;
            } else {
                $validAccountTitles = Accounting::validAccountingJournalEntries();
                $tAccountErrors = [];

                foreach ($value['tAccounts'] as $accountIndex => $tAccount) {
                    $accountErrors = [];

                    // Account title
                    if (empty($tAccount['accountTitle']) || trim($tAccount['accountTitle']) === '') {
                        $accountErrors['accountTitle'] = 'Account title is required.';
                        $hasErrors = true;
                    } elseif (!in_array($tAccount['accountTitle'], $validAccountTitles)) {
                        $accountErrors['accountTitle'] = 'Account title must be from the valid list of accounts.';
                        $hasErrors = true;
                    }

                    // Postings (at least 1, no max). Each row is a real full row - the
                    // instructor fills in a debit amount, a credit amount, or both -
                    // a row is just a shared grid line, so both sides may legitimately
                    // hold independent transactions. Each side is either fully used
                    // (label + amount both present) or fully unused (both blank) -
                    // no partial state (a label with no amount, or an amount with no
                    // label) is allowed on either side.
                    if (!isset($tAccount['postings']) || !is_array($tAccount['postings']) || count($tAccount['postings']) < 1) {
                        $accountErrors['postings'] = ['general' => 'At least one posting is required.'];
                        $hasErrors = true;
                    } else {
                        $postingErrors = [];
                        foreach ($tAccount['postings'] as $postingIndex => $posting) {
                            $postingFieldErrors = [];

                            $hasDebit = !empty($posting['debit']) || (isset($posting['debit']) && $posting['debit'] !== '' && $posting['debit'] !== null);
                            $hasCredit = !empty($posting['credit']) || (isset($posting['credit']) && $posting['credit'] !== '' && $posting['credit'] !== null);
                            $hasDebitLabel = !(empty($posting['debitLabel']) || trim($posting['debitLabel']) === '');
                            $hasCreditLabel = !(empty($posting['creditLabel']) || trim($posting['creditLabel']) === '');

                            if ($hasDebit && !$hasDebitLabel) {
                                $postingFieldErrors['debitLabel'] = 'Debit label (date/number) is required.';
                                $hasErrors = true;
                            }
                            if ($hasDebitLabel && !$hasDebit) {
                                $postingFieldErrors['debit'] = 'Debit amount is required since a debit label was entered.';
                                $hasErrors = true;
                            }
                            if ($hasCredit && !$hasCreditLabel) {
                                $postingFieldErrors['creditLabel'] = 'Credit label (date/number) is required.';
                                $hasErrors = true;
                            }
                            if ($hasCreditLabel && !$hasCredit) {
                                $postingFieldErrors['credit'] = 'Credit amount is required since a credit label was entered.';
                                $hasErrors = true;
                            }

                            if (!$hasDebit && !$hasCredit) {
                                $postingFieldErrors['amount'] = 'Enter an amount on either the debit or credit side.';
                                $hasErrors = true;
                            } else {
                                if ($hasDebit) {
                                    $parsedDebit = $this->parseAmount($posting['debit'] ?? '');
                                    if ($parsedDebit === null) {
                                        $postingFieldErrors['debit'] = 'Amount must be a valid number.';
                                        $hasErrors = true;
                                    } elseif ($parsedDebit < 0) {
                                        $postingFieldErrors['debit'] = 'Amount cannot be negative.';
                                        $hasErrors = true;
                                    }
                                }
                                if ($hasCredit) {
                                    $parsedCredit = $this->parseAmount($posting['credit'] ?? '');
                                    if ($parsedCredit === null) {
                                        $postingFieldErrors['credit'] = 'Amount must be a valid number.';
                                        $hasErrors = true;
                                    } elseif ($parsedCredit < 0) {
                                        $postingFieldErrors['credit'] = 'Amount cannot be negative.';
                                        $hasErrors = true;
                                    }
                                }
                            }

                            if (!empty($postingFieldErrors)) {
                                $postingErrors[$postingIndex] = $postingFieldErrors;
                            }
                        }
                        if (!empty($postingErrors)) {
                            $accountErrors['postings'] = $postingErrors;
                        }
                    }

                    // Balance (optional, at most one row, exactly one side filled in,
                    // and that side's label is required - same as a normal posting).
                    $balance = $tAccount['balance'] ?? null;
                    if (!empty($balance)) {
                        $hasDebit = isset($balance['debit']) && $balance['debit'] !== '' && $balance['debit'] !== null;
                        $hasCredit = isset($balance['credit']) && $balance['credit'] !== '' && $balance['credit'] !== null;

                        if (!$hasDebit && !$hasCredit) {
                            $accountErrors['balance'] = ['amount' => 'Enter the balance on either the debit or credit side.'];
                            $hasErrors = true;
                        } elseif ($hasDebit && $hasCredit) {
                            $accountErrors['balance'] = ['amount' => 'Balance cannot be on both the debit and credit side.'];
                            $hasErrors = true;
                        } else {
                            $side = $hasDebit ? 'debit' : 'credit';
                            $label = $balance[$side . 'Label'] ?? '';
                            if (empty($label) || trim($label) === '') {
                                $accountErrors['balance'] = ['label' => 'Label (date/number) is required.'];
                                $hasErrors = true;
                            }

                            $amountValue = $balance[$side];
                            $parsedAmount = $this->parseAmount($amountValue);
                            if ($parsedAmount === null) {
                                $accountErrors['balance']['amount'] = 'Amount is required and must be a valid number.';
                                $hasErrors = true;
                            } elseif ($parsedAmount < 0) {
                                $accountErrors['balance']['amount'] = 'Amount cannot be negative.';
                                $hasErrors = true;
                            }
                        }
                    }

                    // Beginning Balance (optional, at most one row, exactly one side filled in) -
                    // same shape/rules as the ending Balance, just positioned first and with no
                    // editable label - it's always tagged simply "Beginning Balance".
                    $beginningBalance = $tAccount['beginningBalance'] ?? null;
                    if (!empty($beginningBalance)) {
                        $hasDebit = isset($beginningBalance['debit']) && $beginningBalance['debit'] !== '' && $beginningBalance['debit'] !== null;
                        $hasCredit = isset($beginningBalance['credit']) && $beginningBalance['credit'] !== '' && $beginningBalance['credit'] !== null;

                        if (!$hasDebit && !$hasCredit) {
                            $accountErrors['beginningBalance'] = ['amount' => 'Enter the beginning balance on either the debit or credit side.'];
                            $hasErrors = true;
                        } elseif ($hasDebit && $hasCredit) {
                            $accountErrors['beginningBalance'] = ['amount' => 'Beginning balance cannot be on both the debit and credit side.'];
                            $hasErrors = true;
                        } else {
                            $amountValue = $hasDebit ? $beginningBalance['debit'] : $beginningBalance['credit'];
                            $parsedAmount = $this->parseAmount($amountValue);
                            if ($parsedAmount === null) {
                                $accountErrors['beginningBalance'] = ['amount' => 'Amount is required and must be a valid number.'];
                                $hasErrors = true;
                            } elseif ($parsedAmount < 0) {
                                $accountErrors['beginningBalance'] = ['amount' => 'Amount cannot be negative.'];
                                $hasErrors = true;
                            }
                        }
                    }

                    if (!empty($accountErrors)) {
                        $tAccountErrors[$accountIndex] = $accountErrors;
                    }
                }

                if (!empty($tAccountErrors)) {
                    $this->errors['tAccounts'] = $tAccountErrors;
                }
            }
        }

        return !$hasErrors;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return json_encode($this->errors);
    }
}
