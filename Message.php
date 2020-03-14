<?php

/**
 * Message.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace nterms\mailqueue;

use nterms\mailqueue\models\Queue;
use Yii;

/**
 * Extends `yii\swiftmailer\Message` to enable queuing.
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-message.html
 */
class Message extends \yii\swiftmailer\Message
{
    /**
     * Enqueue the message storing it in database.
     *
     * @param timestamp $time_to_send
     * @return boolean true on success, false otherwise
     */
    public function queue($time_to_send = 'now')
    {
        if ($time_to_send == 'now') {
            $time_to_send = time();
        }
        $queueModelClass = Yii::$app->mailqueue->queueModelClass;
        $item = new $queueModelClass([
            'subject' => $this->getSubject(),
            'attempts' => 0,
            'swift_message' => base64_encode(serialize($this)),
            'time_to_send' => date('Y-m-d H:i:s', $time_to_send),
        ]);


        return $item->save();
    }
}
