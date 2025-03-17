<?php

namespace App\MailStructure;

use App\Mail\NewAccountMail;
use Exception;
use App\Models\AppSetting;
use App\Models\EmailConfig;
use App\Mail\OrderPlaceMail;
use App\Mail\StatusChangeMail;
use App\Mail\ReturnCartOrderMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReturnCartOrderStatusChangeMail;

class MailStructure
{
    /**
     * @throws Exception
     */
    public function EmailConfig(): void
    {
        $emailConfig = EmailConfig::first();
        
        if (!$emailConfig->emailConfigName) {
            throw new Exception("Email config name is not set");
        }

        config([
            'mail.mailers.smtp.host' => $emailConfig->emailHost,
            'mail.mailers.smtp.port' => $emailConfig->emailPort,
            'mail.mailers.smtp.encryption' => $emailConfig->emailEncryption,
            'mail.mailers.smtp.username' => $emailConfig->emailUser,
            'mail.mailers.smtp.password' => $emailConfig->emailPass,
            'mail.mailers.smtp.local_domain' => env('MAIL_EHLO_DOMAIN'),
            'mail.from.address' => $emailConfig->emailUser,
            'mail.from.name' => $emailConfig->emailConfigName,
        ]);
    }

    /**
     * @throws Exception
     */
    public function OrderPlace($userMail, $OrderInfo): void
    {
        $this->EmailConfig();
        //map all product
        $product = array_column($OrderInfo['cart_order_product'], 'product');
        //map all product quantity from cart_order_product
        $productQuantities = array_column($OrderInfo['cart_order_product'], 'productQuantity');
        $appSetting = AppSetting::where('id', 1)->first();

        $data = [
            //invoice info
            'invoiceId' => $OrderInfo['id'],
            'date' => $OrderInfo['date'],
            'totalAmount' => $OrderInfo['totalAmount'],
            'paidAmount' => $OrderInfo['paidAmount'],
            'deliveryFee' => $OrderInfo['deliveryFee'],
            'dueAmount' => $OrderInfo['due'],
            'couponAmount' => $OrderInfo['couponAmount'],
            'deliveryAddress' => $OrderInfo['deliveryAddress'],
            'OrderStatus' => $OrderInfo['orderStatus'],

            //customer info
            'customerName' => $OrderInfo['customer']['username'],
            'customerEmail' => $OrderInfo['customer']['email'],
            'customerPhone' => $OrderInfo['customer']['phone'],

            //product info
            'product' => $product,
            'productQuantities' => $productQuantities,

            //app setting info
            'companyName' => $appSetting['companyName'],
            'tagLine' => $appSetting['tagLine'],
            'address' => $appSetting['address'],
            'phone' => $appSetting['phone'],
            'email' => $appSetting['email'],
            'website' => $appSetting['website'],
            
        ];

        $email = Mail::to($userMail)
            ->send(new OrderPlaceMail('emails.OrderPlace',
                "Your order has been placed!", $data));

        if (!$email) {
            throw new Exception("Email not sent");
        }
    }

    /**
     * @throws Exception
     */
    public function StatusChange($userMail, $OrderInfo): void
    {
        $this->EmailConfig();
        $appSetting = AppSetting::where('id', 1)->first();

        $data = [
            //invoice info
            'invoiceId' => $OrderInfo['id'],
            'date' => $OrderInfo['date'],
            'deliveryAddress' => $OrderInfo['deliveryAddress'],
            'OrderStatus' => $OrderInfo['orderStatus'],
            'deliveryFee' => $OrderInfo['deliveryFee'],

            //customer info
            'customerName' => $OrderInfo['customer']['username'],
            'customerEmail' => $OrderInfo['customer']['email'],
            'customerPhone' => $OrderInfo['customer']['phone'],

             //app setting info
             'companyName' => $appSetting['companyName'],
             'tagLine' => $appSetting['tagLine'],
             'address' => $appSetting['address'],
             'phone' => $appSetting['phone'],
             'email' => $appSetting['email'],
             'website' => $appSetting['website'],
        ];

        $email = Mail::to($userMail)
            ->send(new StatusChangeMail('emails.StatusChange',
                "Your order status has been changed!", $data));

        if (!$email) {
            throw new Exception("Email not sent");
        }
    }

    /**
     * @throws Exception
     */

    public function ReturnOrder($customer, $returnOrder, $returnPoduct, $returnOrderData ): void
    {
        $this->EmailConfig();
        $appSetting = AppSetting::where('id', 1)->first();

        //map all product
        $product = array_column($returnPoduct, 'product');
        $productQuantities = array_column($returnPoduct, 'returnProductQuantity');
        
        $data = [
            //invoice info
            'invoiceId' => $returnOrder['cartOrderId'],
            'date' => $returnOrder['date'],

            //return info
            'totalAmount' => $returnOrder['totalAmount'],
            'note'=> $returnOrder['note'],
            'returnType' => $returnOrder['returnType'],
            'returnCartOrderStatus' => $returnOrder['returnCartOrderStatus'],

            //product info
            'product' => $product,
            'productQuantities' => $productQuantities,

            //customer info
            'customerName' => $customer['username'],
            'customerEmail' => $customer['email'],
            'customerPhone' => $customer['phone'],

             //app setting info
             'companyName' => $appSetting['companyName'],
             'tagLine' => $appSetting['tagLine'],
             'address' => $appSetting['address'],
             'phone' => $appSetting['phone'],
             'email' => $appSetting['email'],
             'website' => $appSetting['website'],
        ];
        
        $email = Mail::to( $customer['email'])
            ->send(new ReturnCartOrderMail('emails.ReturnOrder',
                "Your Return Cart Order Has Been Placed!", $data));

        if (!$email) {
            throw new Exception("Email not sent");
        }
    }

    /**
     * @throws Exception
     */
    public function returnCartOrderStatusChange($customer, $OrderInfo): void
    {
        $this->EmailConfig();
        $appSetting = AppSetting::where('id', 1)->first();

        $data = [
            //invoice info
            'invoiceId' => $OrderInfo['cartOrderId'],
            'date' => $OrderInfo['date'],

            //return info
            'totalAmount' => $OrderInfo['totalAmount'],
            'note'=> $OrderInfo['note'],
            'returnType' => $OrderInfo['returnType'],
            'returnCartOrderStatus' => $OrderInfo['returnCartOrderStatus'],


            //customer info
            'customerName' => $customer['username'],
            'customerEmail' => $customer['email'],
            'customerPhone' => $customer['phone'],

             //app setting info
             'companyName' => $appSetting['companyName'],
             'tagLine' => $appSetting['tagLine'],
             'address' => $appSetting['address'],
             'phone' => $appSetting['phone'],
             'email' => $appSetting['email'],
             'website' => $appSetting['website'],
        ];
        
        $email = Mail::to($customer)
            ->send(new ReturnCartOrderStatusChangeMail('emails.ReturnOrderStatusChange',
                "Your Return Cart order status has been changed!", $data));

        if (!$email) {
            throw new Exception("Email not sent");
        }
    }

    public function newAccount($userMail, $user): void
    {
        $this->EmailConfig();

        
        $email = Mail::to($userMail)
            ->send(new NewAccountMail('emails.NewAccount',
                "Your account has been created!", $user));

        if (!$email) {
            throw new Exception("Email not sent");
        }
    }
}