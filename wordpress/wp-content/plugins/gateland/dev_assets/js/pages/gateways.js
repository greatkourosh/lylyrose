//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandGateways", ()=>({
        pageLoaderIsActive: false,

        //table data
        tableData: [],
        tableLoaderIsActive: false,

        //modals
        modals: {
            status: {
                active: false,
                gateway: null
            },
            delete: {
                active: false,
                gateway: null
            },
        },

        //page data
        gatewaysTableEl: null,
        skeletonIds: [1,2,3,4,5],

        async init(){
            await this.getPageData();
            this.gatewaysTableEl = document.getElementById("gatewaysTable");

            this.skeletonIds = [];
            let htmlTemp = "";
            for (const item of this.tableData) {
                htmlTemp += await this.generateGatewayRow(item);
                this.skeletonIds.push(item.id);
            }

            this.gatewaysTableEl.innerHTML = htmlTemp;

            const sortable = new Draggable.Sortable(this.gatewaysTableEl, {
                draggable: '.gateway-row',
                handle: '.btn-handle',
                mirror: {
                    constrainDimensions: true,
                },
            });

            const submitFunction = ()=>{
                this.submit();
            }
            sortable.on('drag:stop', () => {
                setTimeout(()=>{
                    submitFunction()
                }, 100)
            });

        },

        //request functions
        async getPageData(){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/index', {
                    method: 'POST',
                })

                if(result.success){
                    const data = result.data;
                    this.tableData = data.gateways;
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.pageLoaderIsActive = false;
                }

                this.tableLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }

        },

        async changeSortGateways(data){
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/sort', {
                    method: 'POST',
                    data:{
                        gateway_ids: data
                    }
                })

                if(result.success){
                    await this.init();
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

        async deleteGateway(){
            this.skeletonIds = this.skeletonIds.filter(item => item !== this.modals.delete.gateway.id);
            this.modals.delete.active = false;
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/delete', {
                    method: 'POST',
                    data:{
                        gateway_id: this.modals.delete.gateway.id,
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                    await this.init();
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                    this.tableLoaderIsActive = false;
                    await this.init();
                }

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
                await this.init();
            }

        },

        async changeStatusGateway(){
            this.modals.status.active = false;
            this.tableLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/gateway/change-status', {
                    method: 'POST',
                    data:{
                        gateway_id: this.modals.status.gateway.id,
                        status: this.modals.status.gateway.status === 'inactive' ? 'active' : 'inactive'
                    }
                })

                if(result.success){
                    gatelandNotyf.success(result.message ? result.message : 'درخواست با موفقیت انجام شد.');
                    await this.init();
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

            }catch (error){
                console.error('Error fetching posts:', error);
                this.tableLoaderIsActive = false;
            }
        },

        submit(){
            const gatewayRowElements  = document.querySelectorAll(".gateway-row");
            const dataIdes = {};
            gatewayRowElements.forEach((el, index)=>{
                const id = el.getAttribute('id').toString();
                dataIdes[index] = id;
            })

            this.changeSortGateways(dataIdes);
        },

        async generateGatewayRow(data){

            const dataString = JSON.stringify(data);

            return `
                <div id="${data.id}" class="gateway-${data.id} ${ data.status === 'active' ? 'active' : ''} gateway-row text-sm grid grid-cols-12 bg-white border-b border-gray-200">
                    <div class="md:col-span-7 col-span-8 flex items-center py-4 md:px-5 px-3">
                        <div class="flex items-center gap-3">
                            <button class="btn-handle size-5 min-w-5 opacity-80 hover:opacity-100">
                                <img class="w-full" src="${assetsBaseUrl}/image/icons/dragable.svg">
                            </button>
                            <div class="flex gap-3">
                                <div class="size-10 min-w-10 flex items-center justify-center">
                                    <img class="max-w-full" src="${data.icon}">
                                </div>
                                <div class="text-sm">
                                    <div class="font-semibold text-gray-900 mb-0.5">${data.name}</div>
                                    <div class="text-gray-600">${data.description}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2 col-span-4 flex items-center py-4 md:px-5 px-3">
                        <div 
                            class="w-full flex items-center md:justify-start justify-end gap-0.5"
                        >
                            <div 
                                onclick='changeGatewayStatus(${dataString})'
                                class="${ data.status === 'active' ? 'bg-primary-600' : 'bg-gray-100'} relative inline-flex w-9 h-5 rounded-xl  z-1 cursor-pointer"
                            >
                                <div class="${ data.status === 'active' ? 'right-0.5' : 'left-0.5'} size-4 absolute top-0.5 bg-white rounded-full z-10 shadow-[0_1px_3px_0_#1018281A]">
                                </div>
                            </div>
                            <div 
                                class="md:inline-block hidden rounded-full text-center text-nowrap text-xs text-gray-900 0 px-2 py-1"
                            >
                                ${ data.status === 'active' ? 'فعال' : 'غیرفعال'}
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-3 col-span-full flex items-center md:justify-start justify-end py-4 md:px-5 px-3">
                         <div class="flex items-center sm:gap-2 gap-1">
                            <a
                                    href="?page=gateland-gateways-edit&gateway_id=${data.id}\"
                                    class="size-7 flex items-center justify-center rounded hover:shadow hover:bg-primary-100"
                            >
                                <img src="${assetsBaseUrl}/image/icons/settings.svg">
                            </a>
                            <a
                                    href="?page=gateland-gateway&gateway_id=${data.id}"
                                    class="size-7 flex items-center justify-center rounded hover:shadow hover:bg-success-100"
                            >
                                <img src="${assetsBaseUrl}/image/icons/eye.svg">
                            </a>
                            <button
                                    onclick='deleteGateway(${dataString})'
                                    class="size-7 flex items-center justify-center rounded hover:shadow hover:bg-error-100"
                            >
                                <img src="${assetsBaseUrl}/image/icons/trash.svg">
                            </button>
                        </div>
                    </div>
                </div>
            `
        },

        openDeleteModal(data){
            this.modals.delete.active = true;
            this.modals.delete.gateway = data;
        },

        openStatusModal(data){
            this.modals.status.active = true;
            this.modals.status.gateway = data;
        },

    }))

})

let pageComponent = null;
document.addEventListener('alpine:initialized', () => {
    const el = document.querySelector('#sortGateways');
    pageComponent = Alpine.$data(el); // Get the Alpine component's data
});

function deleteGateway(data){
    pageComponent.openDeleteModal(data)
}

function changeGatewayStatus(data){
    if(data.status === "active") {
        pageComponent.openStatusModal(data)
    }else{
        pageComponent.modals.status.gateway = data;
        pageComponent.changeStatusGateway()
    }
}