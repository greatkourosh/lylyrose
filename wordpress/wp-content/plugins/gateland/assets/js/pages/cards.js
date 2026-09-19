//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("cards", ()=>({
        pageLoaderIsActive: false,

        //table data
        tableData: [],
        tableLoaderIsActive: false,
        skeletonIds: [1,2,3,4,5],

        //modals
        modals: {
            add:{
                active: false,
                data:{
                    cardNumber: {
                        label: "شماره کارت",
                        value: null,
                        errorMsg: ""
                    },
                    name: {
                        label: "نام و نام خانوادگی صاحب کارت",
                        value: null,
                        errorMsg: ""
                    }
                }
            },
            delete:{
                active: false,
                card: {
                    id: '1235',
                }
            },
            edit:{
                active: false,
                loaderIsActive: false,
                data:{
                    name: {
                        label: "نام و نام خانوادگی صاحب کارت",
                        value: null,
                        errorMsg: ""
                    }
                }
            }
        },

        async init(){
            gatelandLoadTippyInPage();

            await this.getCards();
        },

        //request functions
        async getCards(){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/index', {
                    method: 'POST',
                })

                if(result.success){
                    const data = result.data;
                    this.tableData = data.cards.map((card)=>{
                        card.modelMaxAmount = card.max_amount;
                        card.modelMaxQuantity = card.max_quantity;
                        return card;
                    });

                    this.skeletonIds = [];
                    for (const item of this.tableData) {
                        this.skeletonIds.push(item.id);
                    }
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.tableLoaderIsActive = false;
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }

        },
        
        async addCard(){

            //validation
            let hasError = false;
            for (const key in this.modals.add.data) {
                if(!this.modals.add.data[key].value){
                    this.modals.add.data[key].errorMsg = this.modals.add.data[key].label + " نمی تواند خالی باشد. "
                    hasError = true;
                }else{
                    this.modals.add.data[key].errorMsg = "";
                }
            }

           if(this.modals.add.data.cardNumber.value && this.modals.add.data.cardNumber.value.length !== 19){
                this.modals.add.data.cardNumber.errorMsg = this.modals.add.data.cardNumber.label + " باید 16 رقمی باشد. "
                hasError = true;
            }

            if(hasError){
                return
            }

            this.tableLoaderIsActive = true;
            this.modals.add.active = false;
            this.skeletonIds.push(-1);

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/add', {
                    method: 'POST',
                    data:{
                        name: this.modals.add.data.name.value,
                        card_number: gatelandCardNumberToString(this.modals.add.data.cardNumber.value)
                    }
                })

                if(result.success){
                    const data = result.data;
                     this.tableData = data.cards;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');

                    this.skeletonIds.pop();
                    this.skeletonIds.push(data.card_id);
                }else{
                    this.skeletonIds.pop();
                    this.modals.add.active = true;
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
                this.skeletonIds.pop();
            }
            
        },

        async updateCard(){
            //validation
            let hasError = false;
            for (const key in this.modals.edit.data) {
                if(!this.modals.edit.data[key].value){
                    this.modals.edit.data[key].errorMsg = this.modals.edit.data[key].label + " نمی تواند خالی باشد. "
                    hasError = true;
                }else{
                    this.modals.edit.data[key].errorMsg = "";
                }
            }

            if(hasError){
                return
            }

            this.modals.edit.loaderIsActive = true;
            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/update', {
                    method: 'POST',
                    data:{
                        card_id: this.modals.edit.card.id,
                        name: this.modals.edit.data.name.value
                    }
                })

                if(result.success){
                    const data = result.data;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                    this.tableLoaderIsActive = true;
                    this.tableData = data.cards;
                    setTimeout(()=>{
                        this.tableLoaderIsActive = false;
                    }, 1000)
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.modals.edit.loaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.modals.edit.loaderIsActive = false;
            }
        },

        async bulkUpdate(){
            this.tableLoaderIsActive = true;

            const cards = {};
            this.tableData.forEach(card=>{
                cards[card.id] = {
                    max_amount: gatelandPriceToNumber(card.max_amount.toString()),
                    max_quantity: gatelandPriceToNumber(card.max_quantity.toString())
                }
            })

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/bulk-update', {
                    method: 'POST',
                    data:{
                        cards
                    }
                })

                if(result.success){
                    const data = result.data;
                     this.tableData = data.cards;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }
        },

        async toggleStatus(cardId, status){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/change-status', {
                    method: 'POST',
                    data:{
                        card_id: cardId,
                        status
                    }
                })

                if(result.success){
                    const data = result.data;
                     this.tableData = data.cards;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }
        },

        async toggleIsfailover(cardId){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/set-failover', {
                    method: 'POST',
                    data:{
                        card_id: cardId
                    }
                })

                if(result.success){
                    const data = result.data;
                     this.tableData = data.cards;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }
        },
        
        async deleteCard(){
            this.skeletonIds = this.skeletonIds.filter(item => item !== this.modals.delete.card.id);
            this.tableLoaderIsActive = true;
            this.modals.delete.active = false;

            try{

                const result = await gatelandApiRequest('gateland/card-to-card/card/delete', {
                    method: 'POST',
                    data:{
                        card_id:  this.modals.delete.card.id,
                    }
                })

                if(result.success){
                    const data = result.data;
                     this.tableData = data.cards;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                }else{
                    this.skeletonIds.push(this.modals.delete.card.id);
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.skeletonIds.push(this.modals.delete.card.id);
                this.tableLoaderIsActive = false;
            }
        },

        openAddModal(data){
            this.modals.add.active = true;
            this.modals.add.data = {
                cardNumber: {
                    label: "شماره کارت",
                        value: null,
                        errorMsg: ""
                },
                name: {
                    label: "نام و نام خانوادگی صاحب کارت",
                        value: null,
                        errorMsg: ""
                }
            }
        },

        openEditModal(data){
            this.modals.edit.active = true;
            this.modals.edit.card = data;
            this.modals.edit.data = {
                name: {
                    label: "نام و نام خانوادگی صاحب کارت",
                    value: data.name,
                    errorMsg: ""
                }
            }
        },

        openDeleteModal(data){
            this.modals.delete.active = true;
            this.modals.delete.card = data;
        }

    }))

})