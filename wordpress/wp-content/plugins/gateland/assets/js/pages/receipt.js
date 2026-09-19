//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.store('page', {

        printing: false,

        init(){
            window.addEventListener('afterprint', ()=>{
                this.printing = false
            })
        },

        printTransaction(){
            this.printing = true
            setTimeout(()=>{
                const style = document.createElement('style');
                style.setAttribute('id', 'print-style');
                style.innerHTML = `
                        @media print {
                            body {
                                visibility: hidden;
                            }
                            #section-to-print {
                                visibility: visible;
                            },
                           * {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                          }
                        }
                    `;

                document.head.appendChild(style);
                window.print();
            }, 200)
        },

    }),

    Alpine.data("receipt", ()=>({
        pageLoaderIsActive: false,

        //page data
        receipt: null,
        inquiryLoaderIsActive: false,

        //modals
        modals: {
            view:{
                active: false,
            },
            reject: {
                active: false,
                loaderIsActive: false
            },
            accept: {
                active: false,
                data:{
                    amount: {
                        label: "مبلغ مورد تایید",
                        value: null,
                        errorMsg: ""
                    }
                },
                reAccept: false,
                loaderIsActive: false
            }
        },

        async init(){
            const receiptId = gatelandGetQueryParam('receipt_id');
            const receipt = await this.getReceipt(receiptId);

            if(receipt){
                receipt.id = receiptId;
                this.receipt = receipt;
            }
        },

        //request functions
        async getReceipt(receiptId){
            this.pageLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/receipt/view', {
                    method: 'POST',
                    data: {
                        receipt_id: receiptId
                    }
                })

                if(result.success){
                    const data = result.data;
                    this.pageLoaderIsActive = false;
                    return data;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.pageLoaderIsActive = false;
                }


            }catch (error){
                console.error('Error fetching posts:', error);
                this.fromLoaderIsActive = false;
            }

            return false;

        },

        async inquiryCardNumber(receiptId){

            if(this.inquiryLoaderIsActive){
                return
            }
            this.inquiryLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/receipt/inquiry-card-number', {
                    method: 'POST',
                    data: {
                        receipt_id: receiptId
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد!');
                    this.receipt.source_card.name = result.data.card_number_owner;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.inquiryLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.inquiryLoaderIsActive = false;
            }
        },

        async acceptReceipt(receiptId){

            if(this.modals.reject.loaderIsActive){
                return
            }

            //validation
            let hasError = false;
            for (const key in this.modals.accept.data) {
                if(!this.modals.accept.data[key].value){
                    this.modals.accept.data[key].errorMsg = this.modals.accept.data[key].label + " نمی تواند خالی باشد. "
                    hasError = true;
                }else{
                    this.modals.accept.data[key].errorMsg = "";
                }
            }

            if(hasError){
                return
            }

            this.modals.accept.loaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/receipt/accept', {
                    method: 'POST',
                    data: {
                        receipt_id: receiptId,
                        accepted_amount: gatelandPriceToNumber(this.modals.accept.data.amount.value)
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد!');
                    this.modals.accept.loaderIsActive = false;
                    this.modals.accept.active = false;
                    await this.init();
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.modals.accept.loaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.modals.accept.loaderIsActive = false;
            }

        },

        async rejectReceipt(receiptId){

            if(this.modals.reject.loaderIsActive){
                return
            }
            this.modals.reject.loaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/receipt/reject', {
                    method: 'POST',
                    data: {
                        receipt_id: receiptId
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد!');
                    this.modals.accept.loaderIsActive = false;
                    this.modals.reject.active = false;
                    await this.init();
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.modals.reject.loaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.modals.reject.loaderIsActive = false;
            }

        },

        openViewModal(){
            this.modals.view.active = true;
        },

        openRejectModal(){
            this.modals.reject.active = true;
        },

        openAcceptModal(reAccept){
            this.modals.accept.active = true;
            this.modals.accept.reAccept = reAccept;

            this.modals.accept.data.amount.value = "";
            this.modals.accept.data.amount.errorMsg = "";
        },

    }))

})




















