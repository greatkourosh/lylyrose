//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("gatelandPlugins", ()=>({
        pageLoaderIsActive: false,

        //page data
        plugins: {},

        async init(){
            await this.getPageData();
        },

        async getPageData(){
            this.pageLoaderIsActive = true;

            try{

                const result = await gatelandApiRequest('gateland/plugin/list', {
                    method: 'POST'
                })

                if(result.success){
                    this.plugins = result.data
                }else{
                    gatelandNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.pageLoaderIsActive = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.pageLoaderIsActive = false;
            }

        },
    }))

})