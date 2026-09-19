//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("cardToCard", () => ({
        pageLoaderIsActive: false,
        uploadLoaderIsActive: false,

        //page data
        inputs: {
            cardNumber: {
                label: 'شماره کارت',
                value: null,
                errorMsg: "",
            },
            trackingNumber: {
                label: 'شماره پیگیری',
                value: null,
                errorMsg: "",
            },
            amount: {
                label: 'مبلغ تراکنش',
                value: null,
                errorMsg: "",
            },
            receiptImage: {
                label: 'رسید واریز',
                errorMsg: "",
                value: 0,
                fileName: '',
                file: null
            }
        },

        pageDetails: null,

        time: {
            uration: 120, //2 minutes in seconds
            timerInterval: null,
            textTime: null,
            textTimeHours: '00',
            textTimeMinutes: '03',
            textTimeSeconds: '00',
            btnResendIsActive: false
        },

        //table data
        tableData: [],
        tableLoaderIsActive: false,

        //modals
        modals: {
            delete: {
                active: false,
                receipt: {
                    id: '1235',
                }
            },
            view: {
                active: false,
                receipt: {
                    id: '1235',
                }
            }
        },

        async init() {
            await this.getPageDetails();
        },

        //request functions
        async getPageDetails() {
            this.pageLoaderIsActive = true;
            this.tableLoaderIsActive = true;

            try {

                const result = await gatelandApiRequest('gateland/card-to-card/transaction/view', {
                    method: 'POST',
                    headers: {
                        nonce: null
                    },
                    data: {
                        transaction_token: gateland.transaction_token,
                    },
                })

                if (result.success) {
                    const data = result.data;
                    this.pageDetails = data;
                    this.tableData = data.receipts;

                    this.setFavicon(data.bank.logo);

                    if (!this.time.timerInterval) {
                        this.time.duration = data.remain_time * 1000;
                        this.startTime();
                    }
                } else {
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.pageLoaderIsActive = false;
                this.tableLoaderIsActive = false;

            } catch (error) {
                console.error('Error fetching posts:', error);
                this.pageLoaderIsActive = false;
                this.tableLoaderIsActive = false;
            }

        },

        async uploadReceipt() {

            if (this.uploadLoaderIsActive) {
                return
            }

            //validation
            let hasError = false;
            for (const key in this.inputs) {
                if (!this.inputs[key].value) {
                    this.inputs[key].errorMsg = this.inputs[key].label + " نمی تواند خالی باشد. "
                    hasError = true;
                } else {
                    this.inputs[key].errorMsg = "";
                }
            }

            if (this.inputs.cardNumber.value) {
                if (this.inputs.cardNumber.value.length < 19) {
                    this.inputs.cardNumber.errorMsg = this.inputs.cardNumber.label + " باید 16 رقمی باشد. "
                    hasError = true;
                } else if (this.inputs.cardNumber.value.length !== 19 && this.inputs.cardNumber.value.length < 29) {
                    this.inputs.cardNumber.errorMsg = "شماره شبا" + " باید 24 رقمی باشد. "
                    hasError = true;
                }
            }

            if (hasError) {
                return
            }

            try {

                this.uploadLoaderIsActive = true;

                const formData = new FormData();
                formData.append('transaction_token', gateland.transaction_token);
                formData.append('card_number', this.inputs.cardNumber.value);
                formData.append('tracking_number', this.inputs.trackingNumber.value);
                formData.append('amount', gatelandPriceToNumber(this.inputs.amount.value).toString());
                formData.append('receipt', this.inputs.receiptImage.file);

                const result = await gatelandApiRequest('gateland/card-to-card/transaction/upload-receipt', {
                    method: 'POST',
                    headers: {
                        nonce: null
                    },
                    data: formData
                })

                if (result.success) {
                    const data = result.data;
                    this.tableLoaderIsActive = true;
                    this.tableData.unshift(data.receipt)
                    setTimeout(() => {
                        this.tableLoaderIsActive = false;
                    }, 1000)
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');

                    //reset
                    this.inputs.cardNumber.value = null;
                    this.inputs.trackingNumber.value = null;
                    this.inputs.amount.value = null;
                    this.inputs.receiptImage.value = null;
                    this.inputs.receiptImage.file = null;
                    this.inputs.receiptImage.fileName = null;
                } else {
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.uploadLoaderIsActive = false;

            } catch (error) {
                console.error('Error fetching posts:', error);
                this.uploadLoaderIsActive = false;
            }

        },

        async deleteReceipt() {

            this.tableLoaderIsActive = true;
            this.modals.delete.active = false;

            try {

                const result = await gatelandApiRequest('gateland/card-to-card/transaction/delete-receipt', {
                    method: 'POST',
                    headers: {
                        nonce: null
                    },
                    data: {
                        transaction_token: gateland.transaction_token,
                        receipt_id: this.modals.delete.receipt.id,
                    }
                })

                if (result.success) {
                    this.tableData = result.data.receipts;
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                } else {
                    this.modals.delete.active = true;
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.tableLoaderIsActive = false;

            } catch (error) {
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }

            return false;

        },

        startTime() {
            this.time.timerInterval = null;
            clearInterval(this.time.timerInterval);
            this.time.btnResendIsActive = false;
            const endTime = Date.now() + this.time.duration;

            this.time.timerInterval = setInterval(() => {

                const remaining = endTime - Date.now();

                if (remaining <= 0) {
                    clearInterval(this.time.timerInterval);
                    this.time.timerInterval = null;
                    this.time.btnResendIsActive = true;
                    return;
                }

                const totalSeconds = Math.floor(remaining / 1000);
                let hours = Math.floor(totalSeconds / 3600);
                let minutes = Math.floor((totalSeconds % 3600 ) / 60);
                let seconds = Math.floor(totalSeconds % 60);

                hours = hours < 10 ? '0' + hours : hours;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;

                this.time.textTime = `${hours}:${minutes}:${seconds}`;

                this.time.textTimeHours = hours.toString();
                this.time.textTimeMinutes = minutes.toString();
                this.time.textTimeSeconds = seconds.toString();

            }, 1000);
        },

        uploadReceiptImage(event) {
            const maxSize = this.pageDetails.max_file_size;

            const file = event.target.files[0];

            if (file.size > (maxSize * 1024 * 1024)) {
                gatelandNotyf.error(`حداکثر سایز مجاز برای آپلود ${maxSize} مگابایت می‌باشد.`);
                return
            }

            this.inputs.receiptImage.file = event.target.files[0];
            this.inputs.receiptImage.value = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
            this.inputs.receiptImage.fileName = file.name;
        },

        setFavicon(url) {

            if (!url) {
                return
            }

            let type = '';
            const ext = url.split('.').pop().toLowerCase();

            if (ext === 'png') type = 'image/png';
            else if (ext === 'svg') type = 'image/svg+xml';
            else if (ext === 'jpg' || ext === 'jpeg') type = 'image/jpeg';
            else return console.warn('Unsupported format');

            let link = document.querySelector("link[rel*='icon']");
            if (!link) {
                link = document.createElement('link');
                link.rel = 'icon';
                document.head.appendChild(link);
            }
            link.type = type;
            link.href = url;
        },

        openViewModal(data) {
            this.modals.view.active = true;
            this.modals.view.receipt = data;
        },

        openDeleteModal(data) {
            this.modals.delete.active = true;
            this.modals.delete.receipt = data;
        }

    }))

})