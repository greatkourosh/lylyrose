(() => {

    const {getSetting} = window.wc.wcSettings;
    const {registerPaymentMethod} = window.wc.wcBlocksRegistry;
    const {createElement} = window.wp.element;
    const {decodeEntities} = window.wp.htmlEntities;

    const settings = getSetting(gateland_wc_payment_block.name + '_data', {});
    const label = decodeEntities(settings.title) || 'گیت‌لند';
    const label_element = createElement('span', null, label);

    const icon_element = createElement('img', {
        src: settings.icon,
        alt: decodeEntities(settings.title),
        style: {marginInline: '10px'},
    });

    const title_element = createElement(
        'span',
        {
            style: {
                display: 'flex',
                justifyContent: 'space-between',
                width: '100%',
            },
        },
        [label_element, icon_element]
    );

    const description = () =>
        decodeEntities(
            settings.description ||
            'پرداخت امن به وسیله کلیه کارت‌های عضو شتاب'
        );

    const description_element = createElement(description);

    const payment_method_block = {
        name: gateland_wc_payment_block.name,
        label: title_element,
        content: description_element,
        edit: description_element,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports,
        },
    };

    registerPaymentMethod(payment_method_block);
})();

