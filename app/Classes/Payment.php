<?php

namespace App\Classes;

class Payment
{
    private $id;

    private $date_created;

    private $date_approved;

    private $payment_method_id;

    private $payment_type_id;

    private $status;

    private $currency_id;

    private $description;

    private $collector_id;

    private $payer;
    
    private $transaction_details;

    private $installments;
    
    public function __construct($payment)
    {
      $this->payment = json_decode($payment);

      $this->id                   = $this->payment->id;
      $this->date_created         = $this->payment->date_created;
      $this->date_approved        = $this->payment->date_approved;
      $this->payment_method_id    = $this->payment->payment_method_id;
      $this->payment_type_id      = $this->payment->payment_type_id;
      $this->status               = $this->payment->status;
      $this->currency_id          = $this->payment->currency_id;
      $this->description          = $this->payment->description;
      $this->collector_id         = $this->payment->collector_id;
      $this->payer                = $this->payment->payer;
      $this->transaction_details  = $this->payment->transaction_details;
      $this->installments         = $this->payment->installments;
    }
}