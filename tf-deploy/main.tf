provider "aws" {
    region = "us-east-1"
    profile = "cosc349"
}

resource "aws_security_group" "allow_ssh" {
    name        = "allow_ssh"
    description = "Allow inbound SSH traffic"

    ingress {
        description = "SSH from anywhere"
        from_port   = 22
        to_port     = 22
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    egress {
        from_port   = 0
        to_port     = 0
        protocol    = "-1"
        cidr_blocks = ["0.0.0.0/0"]
    }
}

resource "aws_instance" "web_server" {
    ami           = "ami-0360c520857e3138f"
    instance_type = "t2.micro"
    key_name      = "vockey"

    vpc_security_group_ids = [aws_security_group.allow_ssh.id]

    user_data = <<-EOF
        #!/bin/bash
        sudo apt update
        sudo apt install -y apache2
        sudo systemctl start apache2
        sudo systemctl enable apache2
    EOF

    tags = {
        Name = "WebServer"
    }
}

resource "aws_security_group" "allow_web" {
    name        = "allow_web"
    description = "Allow inbound web traffic"

    ingress {
        description = "HTTP from anywhere"
        from_port   = 80
        to_port     = 80
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    ingress {
        description = "HTTPS from anywhere"
        from_port   = 443
        to_port     = 443
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    egress {
        from_port   = 0
        to_port     = 0
        protocol    = "-1"
        cidr_blocks = ["0.0.0.0/0"]
    }
}

output "web_server_ip" {
    value = aws_instance.web_server.public_ip
    vpc_security_group_ids = [
        aws_security_group.allow_ssh.id,
        aws_security_group.allow_web.id
    ]
}
