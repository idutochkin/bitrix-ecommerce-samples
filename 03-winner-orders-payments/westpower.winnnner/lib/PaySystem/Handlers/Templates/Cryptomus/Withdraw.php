<h1>Вывод выигрыша</h1>
<p><b>Заказ №#ORDER_ID#</b> на сумму <b>#ORDER_SUM#</b></p>
<form action="" method="POST">
    #SESSID#
    <div class="ui-ctl-wrapper">
        <!--div class="ui-ctl-inline ui-ctl-w10">Валюта:</div-->
        <div class="ui-ctl ui-ctl-textbox ui-ctl-inline"><input type="text" name="to_currency" value="USDT" readonly placeholder="Валюта" class="ui-ctl-element"></div>
    </div>
    <div class="ui-ctl-wrapper">
        <!--div class="ui-ctl-inline ui-ctl-w10">Сеть:</div-->
        <div class="ui-ctl ui-ctl-textbox ui-ctl-inline"><input type="text" name="network" value="TRON" readonly placeholder="Сеть" class="ui-ctl-element"></div>
    </div>
    <div class="ui-ctl-wrapper">
        <!--div class="ui-ctl-inline ui-ctl-w10">Адрес:</div-->
        <div class="ui-ctl ui-ctl-textbox ui-ctl-inline"><input type="text" name="address" placeholder="Адрес" class="ui-ctl-element"></div>
    </div>
    <div class="ui-ctl-wrapper">
        <input type="submit" value="Вывести" class="ui-btn ui-btn-success">
    </div>
</form>
<p><a href="/">Перейти на главную.</a></p>