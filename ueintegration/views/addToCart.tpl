{literal}
<script language=javascript>
    setTimeout(function () {/literal}{ldelim}{literal}
        prestashop.on('updateCart', function () {/literal}{ldelim}{literal}
            userengage('event.addToCart', {/literal}{ldelim}{literal}
                'price': '{/literal}{$price}{literal}',
                'image_url': '{/literal}{$image_url}{literal}',
                'id': '{/literal}{$id}{literal}',
                'product_url': '{/literal}{$product_url}{literal}',
                'name': '{/literal}{$name}{literal}',
                'category': '{/literal}{$category}{literal}',
                'quantity': '{/literal}{$quantity}{literal}',
                {/literal}{rdelim}{literal});
            {/literal}{rdelim}{literal});
        {/literal}{rdelim}{literal}, 700);
</script>
{/literal}