import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.BloomeryConfirm = {
    async show({ title, text, confirmText, icon = 'warning', confirmColor = '#d97706' }) {
        const result = await Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Batal',
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#ffffff',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                container: 'bloomery-swal-container',
                popup: 'bloomery-swal-popup',
                title: 'bloomery-swal-title',
                htmlContainer: 'bloomery-swal-text',
                actions: 'bloomery-swal-actions',
                confirmButton: 'bloomery-swal-confirm',
                cancelButton: 'bloomery-swal-cancel',
            },
        });

        return result.isConfirmed;
    },
};
