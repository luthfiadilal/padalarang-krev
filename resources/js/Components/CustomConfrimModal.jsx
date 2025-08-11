import { Icon } from '@iconify/react';
import { Button, Modal } from 'flowbite-react';
import { motion } from 'framer-motion';

export default function CustomConfirmModal({
    show,
    onClose,
    onConfirm,
    message,
}) {
    return (
        <Modal
            show={show}
            onClose={onClose}
            dismissible
            className="!backdrop-blur-sm"
        >
            <Modal.Header className="rounded-t-xl bg-gradient-to-r from-blue-500 to-blue-800 p-4 text-white shadow-md">
                <span className="font-semibold text-lg text-white">
                    Konfirmasi
                </span>
            </Modal.Header>

            <Modal.Body>
                <motion.div
                    initial={{ scale: 0.8, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    transition={{ type: 'spring', stiffness: 200 }}
                    className="text-center"
                >
                    <Icon
                        icon="ic:outline-warning-amber"
                        className="mx-auto mb-4 h-16 w-16 text-yellow-400"
                    />
                    <h3 className="font-medium mb-5 text-lg text-gray-600 dark:text-gray-300">
                        {message}
                    </h3>
                </motion.div>
            </Modal.Body>

            <Modal.Footer className="flex justify-center gap-3 border-t pt-4">
                <Button
                    color="failure"
                    className="rounded-lg bg-green-600 px-5 py-2 shadow-md transition hover:shadow-lg"
                    onClick={onConfirm}
                >
                    Ya, saya yakin
                </Button>
                <Button
                    color="gray"
                    className="rounded-lg bg-gray-300 px-5 py-2 shadow transition hover:shadow-lg"
                    onClick={onClose}
                >
                    Tidak, Belum
                </Button>
            </Modal.Footer>
        </Modal>
    );
}
