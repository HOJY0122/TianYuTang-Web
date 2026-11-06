<?php
/**
 * Live IC / phone checks, the same as the public registration form:
 * fields marked data-validate="ic" / "phone" show their problem under the
 * field while typing, and the event's age rule (Forms settings) is checked
 * from the IC with "born 1950 · age 76" shown. The server checks again.
 * Needs $event.
 */
$ageForBrowser = App\Core\FormRules::ageForBrowser($event);
?>
<script src="<?= asset('js/validate.js') ?>"></script>
<?php if ($ageForBrowser !== null): ?>
<script>window.TYTValidate && window.TYTValidate.useAge(<?= json_encode($ageForBrowser) ?>);</script>
<?php endif; ?>
