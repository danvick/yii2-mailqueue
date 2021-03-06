<?php

/**
 * MailQueue.php
 * @author Saranga Abeykoon http://nterms.com
 */

namespace nterms\mailqueue;

use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Connection;
use yii\db\Expression;
use yii\di\Instance;
use yii\swiftmailer\Mailer;
use nterms\mailqueue\Message;
use nterms\mailqueue\models\Queue;

/**
 * MailQueue is a sub class of [yii\switmailer\Mailer](https://github.com/yiisoft/yii2-swiftmailer/blob/master/Mailer.php)
 * which intends to replace it.
 *
 * Configuration is the same as in `yii\switmailer\Mailer` with some additional properties to control the mail queue
 *
 * ~~~
 * 	'components' => [
 * 		...
 * 		'mailqueue' => [
 * 			'class' => 'nterms\mailqueue\MailQueue',
 *			'table' => '{{%mail_queue}}',
 *			'mailsPerRound' => 10,
 *			'maxAttempts' => 3,
 * 			'transport' => [
 * 				'class' => 'Swift_SmtpTransport',
 * 				'host' => 'localhost',
 * 				'username' => 'username',
 * 				'password' => 'password',
 * 				'port' => '587',
 * 				'encryption' => 'tls',
 * 			],
 * 		],
 * 		...
 * 	],
 * ~~~
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
 * @see http://www.yiiframework.com/doc-2.0/ext-swiftmailer-index.html
 *
 * This extension replaces `yii\switmailer\Message` with `nterms\mailqueue\Message'
 * to enable queuing right from the message.
 *
 */
class MailQueue extends Mailer
{
	const NAME = 'mailqueue';

	/**
	 * @var string message default class name.
	 */
	public $messageClass = 'nterms\mailqueue\Message';

    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbManager object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var ActiveRecord|string
     */
    public $queueModelClass = 'nterms\mailqueue\models\Queue';

	/**
	 * @var string the name of the database table to store the mail queue.
	 */
	public $table = '{{%mail_queue}}';

	/**
	 * @var integer the default value for the number of mails to be sent out per processing round.
	 */
	public $mailsPerRound = 10;

	/**
	 * @var integer maximum number of attempts to try sending an email out.
	 */
	public $maxAttempts = 3;
	
	
	/**
	 * @var boolean Purges messages from queue after sending
	 */
	public $autoPurge = true;

    /**
     * Initializes the MailQueue component.
     * @throws InvalidConfigException
     */
	public function init()
	{
        $this->db = Instance::ensure($this->db, Connection::className());
        $this->queueModelClass = Instance::ensure($this->queueModelClass, ActiveRecord::className());
		parent::init();
	}

    /**
     * Sends out the messages in email queue and update the database.
     *
     * @return boolean true if all messages are successfully sent out
     * @throws InvalidConfigException
     */
	public function process()
	{
		if ($this->db->getTableSchema($this->table) == null) {
			throw new InvalidConfigException('"' . $this->table . '" not found in database. Make sure the db migration is properly done and the table is created.');
		}
		
		$success = true;

		$items = ($this->queueModelClass)::find()->where(['and', ['sent_time' => NULL], ['<', 'attempts', $this->maxAttempts], ['<=', 'time_to_send', date('Y-m-d H:i:s')]])->orderBy(['created_at' => SORT_ASC])->limit($this->mailsPerRound);
		foreach ($items->each() as $item) {
		    if ($message = $item->toMessage()) {
			$attributes = ['attempts', 'last_attempt_time'];
			if ($this->send($message)) {
			    $item->sent_time = new Expression('NOW()');
			    $attributes[] = 'sent_time';
			} else {
			    $success = false;
			}

			$item->attempts++;
			$item->last_attempt_time = new Expression('NOW()');

			$item->updateAttributes($attributes);
		    }
		}
	
		// Purge messages now?
		if ($this->autoPurge) {
			$this->purge();
		}

		return $success;
	}
	
	
	/**
	 * Deletes sent messages from queue.
	 *
	 * @return int Number of rows deleted
	 */
	
	public function purge()
	{
		return ($this->queueModelClass)::deleteAll('sent_time IS NOT NULL');
	}
}
