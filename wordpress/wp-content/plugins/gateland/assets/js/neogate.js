document.addEventListener('alpine:init', () => {

    Alpine.data("neogate", ()=>({
        pageLoaderIsActive: false,

        timeLeft: 0, // 2 minutes in seconds
        timerInterval: null,
        timeText: '15:00',

        cancelModal: false,

        init(){
            this.timerInterval = setInterval(() => this.timeCountdown(), 1000);
        },

        timeCountdown() {
            let minutes = Math.floor(this.timeLeft / 60);
            let seconds = this.timeLeft % 60;

            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            this.timeText = `${minutes}:${seconds}`;

            if (this.timeLeft > 0) {
                this.timeLeft--;
            } else {
                clearInterval(this.timerInterval);
            }
        },

        copyToClipboard(el) {
            if (!navigator.clipboard) {
                // Clipboard API not available
                console.error('Clipboard API not available');
                return;
            }

            const textValue = el.getAttribute('data-copy');
            const textCopyEl = el.querySelector('.text-copy');

            navigator.clipboard.writeText(textValue)
                .then(() => {
                    //console.log('Copied to clipboard:', textValue);
                    if(textCopyEl){
                        textCopyEl.textContent = "کپی شد"
                    }
                })
                .catch((err) => {
                    console.error('Failed to copy:', err);
                });
        },

        openCancelModal(){
            this.cancelModal = true;
        },

    }))

})