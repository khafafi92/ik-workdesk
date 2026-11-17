@php([$moneySymbol, $moneyNumber] = explode(' ', $poMoney((int) $amount), 2))
<span class="scm-po-amount"><span class="scm-po-amount-symbol">{{ $moneySymbol }}</span> <span class="scm-po-amount-number">{{ $moneyNumber }}</span></span>
