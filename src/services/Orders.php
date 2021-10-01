<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\FieldEvent;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
use yii\base\Component;
use yii\base\Exception;

/**
 * Orders service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Orders extends Component
{
    const CONFIG_FIELDLAYOUT_KEY = 'commerce.orders.fieldLayouts';


    /**
     * Handle field layout change
     *
     * @param ConfigEvent $event
     * @throws Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty($config = reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Order::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Order::class)->id;
        $layout->type = Order::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout);
    }


    /**
     * Prune a deleted field from order field layouts.
     *
     * @param FieldEvent $event
     */
    public function pruneDeletedField(FieldEvent $event): void
    {
        /** @var Field $field */
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY);

        // Prune the UID from field layouts.
        if (is_array($layoutData)) {
            foreach ($layoutData as $layoutUid => $layout) {
                if (!empty($layout['tabs'])) {
                    foreach ($layout['tabs'] as $tabUid => $tab) {
                        $projectConfig->remove(self::CONFIG_FIELDLAYOUT_KEY . '.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                    }
                }
            }
        }
    }

    /**
     * Handle field layout being deleted
     *
     * @param ConfigEvent $event
     */
    public function handleDeletedFieldLayout(ConfigEvent $event): void
    {
        Craft::$app->getFields()->deleteLayoutsByType(Order::class);
    }

    /**
     * Get an order by its ID.
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id): ?Order
    {
        if (!$id) {
            return null;
        }

        $query = Order::find();
        $query->id($id);
        $query->status(null);

        return $query->one();
    }

    /**
     * Get an order by its number.
     *
     * @param string $number
     * @return Order|null
     */
    public function getOrderByNumber(string $number): ?Order
    {
        $query = Order::find();
        $query->number($number);

        return $query->one();
    }

    /**
     * Get all orders by their customer.
     *
     * @param int|User $customer
     * @return Order[]|null
     */
    public function getOrdersByCustomer($customer): ?array
    {
        if (!$customer) {
            return null;
        }

        $query = Order::find();
        if ($customer instanceof User) {
            $query->customer($customer);
        } else {
            $query->customerId($customer);
        }
        $query->isCompleted();
        $query->limit(null);

        return $query->all();
    }

    /**
     * Get all orders by their email.
     *
     * @param string $email
     * @return Order[]|null
     */
    public function getOrdersByEmail(string $email): ?array
    {
        $query = Order::find();
        $query->email($email);
        $query->isCompleted();
        $query->limit(null);

        return $query->all();
    }
}
