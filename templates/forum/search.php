<? use Studip\Button; ?>
<table border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
    <tr>
        <td class="blank">

            <table border="0" width="604" cellspacing="5" cellpadding="0" align="center">
                <tr>
                    <td class="blank" width="302" align="center">

                        <form name="search" method="post" action="<?= URLHelper::getLink('') ?>">
                            <?= CSRFProtection::tokenTag() ?>

                            <table cellpadding="2" cellspacing="0" border="0">
                                <tr class="steel1">
                                    <td style="font-weight: bold;">
                                        <label for="suchbegriff"><?= _('Suchbegriff:') ?></label>
                                    </td>
                                    <td class="steel1" style="text-align: right;">
                                        <input type="text" name="suchbegriff" id="suchbegriff">
                                    </td>
                                </tr>
                                <tr class="steel1">
                                    <td style="font-weight: bold;">
                                        <label for="author"><?= _('Von:') ?></label>
                                    </td>
                                    <td>
                                        <input type="text" name="author" id="author">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="steelgraulight" colspan="2" align="center">
                                        <input type="hidden" name="view" value="search">
                                        <br>
                                        <?= Button::create(_('Suchen')) ?>
                                        <br><br>
                                    </td>
                                </tr>
                            </table>
                        </form>

                    </td>
                </tr>
            </table>
            <br>

        </td>
    </tr>
</table>