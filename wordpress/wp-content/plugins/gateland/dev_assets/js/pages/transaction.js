//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandTransaction", ()=>({
        pageLoaderIsActive: false,

        //page data
        transaction: null,
        inquiryLoaderIsActive: false,
        refundLoaderIsActive: false,

        //modals
        modals: {
            refund: {
                active: false,
                transaction: null,
                data:{
                    type: {
                        value: 'manual',  //auto
                        isInput: false,
                    },
                    refund_id: {
                        label: "شناسه استرداد",
                        value: null,
                        errorMsg: "",
                        isInput: true,
                    },
                    amount: {
                        label: "مبلغ استرداد",
                        value: null,
                        errorMsg: "",
                        isInput: true,
                    },
                    description: {
                        label: "توضیحات",
                        value: null,
                        errorMsg: "",
                        isInput: true,
                    },
                }
            },
        },

        async init(){
            const transaction = await this.getTransaction(gatelandGetQueryParam('transaction_id'));

            if(transaction){
                this.transaction = transaction;
            }
        },

        //request functions
        async getTransaction(transactionId){
            this.pageLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/transaction/view', {
                    method: 'POST',
                    data: {
                        transaction_id: transactionId
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
        
        async inquiryTransaction(transactionId){
            this.inquiryLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/transaction/inquiry', {
                    method: 'POST',
                    data: {
                        transaction_id: transactionId
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد');
                    this.inquiryLoaderIsActive = false;
                    await this.getTransaction(transactionId);
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.inquiryLoaderIsActive = false;
                }

            }catch (error){
                console.error('Error fetching posts:', error);
                this.fromLoaderIsActive = false;
            }

        },

        async refundTransaction(transactionId){

            if(this.refundLoaderIsActive){
                return
            }

            //validation
            let hasError = false;
            for (const key in this.modals.refund.data) {
                if(this.modals.refund.data[key].isInput){
                    if(key === 'refund_id'){

                        if(this.modals.refund.data.type.value === 'manual'){
                            if(!this.modals.refund.data[key].value){
                                this.modals.refund.data[key].errorMsg = this.modals.refund.data[key].label + " نمی تواند خالی باشد. "
                                hasError = true;
                            }else{
                                this.modals.refund.data[key].errorMsg = "";
                            }
                        }else{
                            this.modals.refund.data[key].errorMsg = "";
                        }

                    }else{

                        if(!this.modals.refund.data[key].value){
                            this.modals.refund.data[key].errorMsg = this.modals.refund.data[key].label + " نمی تواند خالی باشد. "
                            hasError = true;
                        }else{
                            this.modals.refund.data[key].errorMsg = "";
                        }

                    }
                }
            }

            if(hasError){
                return
            }

            try{

                this.refundLoaderIsActive = true;

                const result = await gatelandApiRequest('gateland/transaction/refund', {
                    method: 'POST',
                    data: {
                        transaction_id: transactionId,
                        amount: gatelandPriceToNumber(this.modals.refund.data.amount.value),
                        description: this.modals.refund.data.description.value,
                        refund_id: this.modals.refund.data.type.value === 'manual' ? this.modals.refund.data.refund_id.value : null
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد!');
                    this.modals.refund.active = false;
                    await this.init();
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.refundLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.pageLoaderIsActive = false;
            }

        },

        openRefundModal(){

            for (const key in this.modals.refund.data) {
                if(this.modals.refund.data[key].isInput){
                    this.modals.refund.data[key].value = null;
                    this.modals.refund.data[key]. errorMsg = "";
                }
            }

            this.modals.refund.active = true;

            this.modals.refund.transaction = this.transaction;

            if(this.transaction.gateway_features.includes('RefundFeature')){
                this.modals.refund.data.type.value = 'auto';
            }else{
                this.modals.refund.data.type.value = 'manual'
            }

        },

    }))

})




















