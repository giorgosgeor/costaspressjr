<?php

/**
 * The EU model withdrawal form (Consumer Rights Directive, Annex I(B)), with
 * the trader's details filled in from Business.
 *
 * A pre-made design can be cancelled within 14 days of collecting it, and the
 * law wants this form offered with that right: on the refund policy page,
 * and in the order confirmation email (OrderConfirmation), which is the
 * "durable medium" copy. Both render these lines, so they never differ.
 * Customers don't have to use it; any clear statement is enough.
 */
final class WithdrawalForm
{
    /** @return string[] the form's lines, plain text, in the current language */
    public static function lines(): array
    {
        $b = Business::details();
        $trader = implode(', ', array_filter([
            $b['name'] !== '' ? $b['name'] : I18n::t('site.brand'),
            implode(', ', $b['address']),
            $b['email'],
        ], 'strlen'));

        return [
            I18n::t('withdrawal.note'),
            I18n::t('withdrawal.to', ['trader' => $trader]),
            I18n::t('withdrawal.notice'),
            I18n::t('withdrawal.order'),
            I18n::t('withdrawal.dates'),
            I18n::t('withdrawal.name'),
            I18n::t('withdrawal.address'),
            I18n::t('withdrawal.signature'),
            I18n::t('withdrawal.date'),
            I18n::t('withdrawal.footnote'),
        ];
    }
}
