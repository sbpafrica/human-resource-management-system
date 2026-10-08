import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<Required<LoginForm>>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
            onError: (errors) => {
                console.error('Login errors:', errors);
            },
        });
    };

    return (
        <div className="min-h-screen flex flex-col md:flex-row bg-white">
            <Head title="Login" />
            {/* Left Panel */}
            <div className="w-full md:w-1/2 bg-white flex flex-col justify-between pt-16">
                <div className="px-[15%]">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2 mt-12">Get Started Now</h1>
                    <p className="text-gray-600 mb-6">Enter your credentials to access your account</p>

                    {status && <div className="mb-4 text-sm text-green-600">{status}</div>}

                    <form className="space-y-6" onSubmit={submit}>
                        <div className="space-y-4">
                            {/* Email */}
                            <div>
                                <Label htmlFor="email" className="text-gray-700">
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full p-3 rounded-lg shadow-sm border border-gray-300"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            {/* Password */}
                            <div>
                                <Label htmlFor="password" className="text-gray-700">
                                    Password
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="w-full p-3 rounded-lg shadow-sm border border-gray-300"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            {/* Remember Checkbox */}
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    checked={data.remember}
                                    onClick={() => setData('remember', !data.remember)}
                                />
                                <Label htmlFor="remember" className="text-gray-700">
                                    Remember me
                                </Label>
                            </div>


                        </div>

                        {/* Login Button */}
                        <Button
                            type="submit"
                            className="w-full bg-blue-600 text-white p-3 rounded-xl hover:bg-blue-700 transition duration-200"
                            disabled={processing}
                        >
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin mr-2" />}
                            Login
                        </Button>
                    </form>

                    <div className="text-center text-sm text-gray-500 mt-30 pb-6">
                        2025 SBPAfrica, All rights reserved
                    </div>
                </div>
            </div>

            {/* Right Panel */}
            <div className="hidden md:flex w-1/2 bg-blue-600 p-16 flex-col justify-between text-white rounded-3xl m-[1%] ml-[20%]">
                <div>
                    <h1 className="text-3xl font-bold mb-4">The simplest way to manage your workforce</h1>
                    <p className="mb-6">Enter your credentials to access your account</p>
                    <div className="h-110 flex items-center justify-center mb-[-10%] rounded-lg overflow-hidden">
                        <img
                            src="/images/loginimage.png"
                            alt="Login Illustration"
                            className="object-cover h-full w-full rounded-lg"
                        />
                    </div>
                </div>
                <div className="text-center text-sm">Powered by SBP Africa</div>
            </div>
        </div>
    );
}
