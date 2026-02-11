import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('xit-act-modal');
    const jsonPre = document.getElementById('xit-act-json');
    const copyBtn = document.getElementById('xit-act-copy');

    document.querySelectorAll('.task[data-xit-act]').forEach(task => {
        task.addEventListener('click', () => {
            const json = task.dataset.xitAct;
            const formatted = JSON.stringify(JSON.parse(json), null, 2);
            jsonPre.textContent = formatted;
            modal.classList.remove('modal--hidden');
        });
    });

    const closeModal = () => modal.classList.add('modal--hidden');

    modal.querySelector('.modal__close').addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('modal--hidden')) {
            closeModal();
        }
    });

    copyBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(jsonPre.textContent).then(() => {
            copyBtn.textContent = 'Copied!';
            setTimeout(() => { copyBtn.textContent = 'Copy to Clipboard'; }, 2000);
        });
    });
});
