<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\LiteShippingSettings;
use craft\commerce\Plugin;
use craft\errors\WrongEditionException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LiteShippingController extends BaseStoreSettingsController
{
    /**
     * @throws WrongEditionException
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();

        if (!Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            throw new WrongEditionException('Lite settings editable when using the lite edition only');
        }
    }

    /**
     * Commerce Settings Form
     */
    public function actionEdit(): Response
    {
        $settings = new LiteShippingSettings(['shippingBaseRate' => 0, 'shippingPerItemRate' => 0]);

        $shippingRule = Plugin::getInstance()->getShippingRules()->getLiteShippingRule();
        $settings->shippingBaseRate = $shippingRule->getBaseRate();
        $settings->shippingPerItemRate = $shippingRule->getPerItemRate();

        return $this->renderTemplate('commerce/store-settings/shipping/index', compact('settings'));
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $settings = new LiteShippingSettings();
        $settings->shippingPerItemRate = $this->request->getBodyParam('shippingPerItemRate');
        $settings->shippingBaseRate = $this->request->getBodyParam('shippingBaseRate');

        if (!$settings->validate()) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save shipping settings.'));
            return $this->renderTemplate('commerce/store-settings/shipping', compact('settings'));
        }

        $shippingMethod = Plugin::getInstance()->getShippingMethods()->getLiteShippingMethod();
        $shippingMethodSaved = Plugin::getInstance()->getShippingMethods()->saveLiteShippingMethod($shippingMethod, false);

        $shippingRule = Plugin::getInstance()->getShippingRules()->getLiteShippingRule();
        $shippingRule->baseRate = $settings->shippingBaseRate;
        $shippingRule->perItemRate = $settings->shippingPerItemRate;
        $shippingRule->methodId = $shippingMethod->id;
        $shippingRuleSaved = Plugin::getInstance()->getShippingRules()->saveLiteShippingRule($shippingRule, false);

        if (!$shippingMethodSaved || !$shippingRuleSaved) {
            throw new Exception('Could not save internal shipping method or rule for lite shipping');
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
