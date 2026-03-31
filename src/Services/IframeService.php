<?php

namespace AlRajhi\PaymentGateway\Services;

class IframeService extends BankHostedService
{
    public function initiate(array $data): array
    {
        $data['is_iframe'] = true;
        $response = parent::initiate($data);

        if ($response['success']) {
            $response['iframe_html'] = $this->getIframeHtml(
                (string) $response['payment_url'],
                (string) $response['payment_id']
            );
        }

        return $response;
    }

    public function getIframeHtml(string $paymentUrl, string $paymentId): string
    {
        return <<<HTML
<script>
if(window.parent.document.getElementById("iframe")!=null) {
    var division=document.createElement("div");
    division.setAttribute("id","payframe");
    division.setAttribute("style","min-height: 100%; position: fixed; top: 0px; left: 0px; width: 100%; height: 100%; background: rgba(0, 0, 0, 0); padding-right: 0px; padding-left: 0px;padding-top: 0px;");
    division.innerHTML= ' <div style="position: absolute;right: 0px;top: 0px;cursor: pointer;font-size: 24px;opacity:.6;width: 100%;text-align: center;line-height: 0px;z-index: 1;" class="close" id="F" onclick="javascript: window.parent.document.getElementById(\\'iframe\\').parentNode.removeChild(window.parent.document.getElementById(\\'iframe\\'));window.parent.document.getElementById(\\'payframe\\').parentNode.removeChild(window.parent.document.getElementById(\\'payframe\\'));">x</div><iframe id="iframe" style="opacity: 7; height: 100%; position: relative; background: none; display: block; border: 0px none transparent; margin-left: 0%; padding: 0px; z-index: 2; width: 100%; margin-top: 0%" allowtransparency="true" frameborder="0" allowpaymentrequest="true" src="{$paymentUrl}?PaymentID={$paymentId}"></iframe> ';
    document.body.appendChild(division);
} else {
    var division=document.createElement("div");
    division.setAttribute("id","payframe");
    division.setAttribute("style", "min-height: 100%; transition: all 0.3s ease-out 0s; position: fixed; top: 0px; left: 0px; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); padding-right: 10px; padding-left: 250px;padding-top: 0px;");
    division.innerHTML='<div style="position: absolute;right: 0px;top: 0px;cursor: pointer;font-size: 24px;opacity:.6;width: 24px;text-align: center;line-height: 0px;z-index: 1;" class="close" id="F" onclick="javascript: window.parent.document.getElementById(\\'iframe\\').parentNode.removeChild(window.parent.document.getElementById(\\'iframe\\'));window.parent.document.getElementById(\\'payframe\\').parentNode.removeChild(window.parent.document.getElementById(\\'payframe\\'));">x</div><iframe id="iframe" style="opacity: 7; height: 100%; position: relative; background: none; display: block; border: 0px none transparent; margin-left: 7%; padding: 0px; z-index: 2; width: 65%; margin-top: 0%" allowtransparency="true" frameborder="0" allowpaymentrequest="true" src="{$paymentUrl}?PaymentID={$paymentId}"></iframe> ';
    document.body.appendChild(division);
}
</script>
HTML;
    }
}
