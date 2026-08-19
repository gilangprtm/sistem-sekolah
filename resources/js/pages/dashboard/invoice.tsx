'use client';

import { FormProvider, useForm, useWatch } from 'react-hook-form';

import { defaultInvoiceValues } from './invoice/data';
import type { InvoiceFormValues } from './invoice/data';
import { InvoiceForm } from './invoice/invoice-form';
import { InvoicePreview } from './invoice/invoice-preview';

export default function Invoice() {
    const form = useForm<InvoiceFormValues>({
        defaultValues: defaultInvoiceValues,
    });
    const invoice = useWatch({ control: form.control }) as InvoiceFormValues;

    return (
        <FormProvider {...form}>
            <form
                className="grid gap-5 xl:grid-cols-2"
                noValidate
                onSubmit={(event) => event.preventDefault()}
            >
                <InvoiceForm />
                <InvoicePreview invoice={invoice} />
            </form>
        </FormProvider>
    );
}
